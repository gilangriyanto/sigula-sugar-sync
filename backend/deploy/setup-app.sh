#!/usr/bin/env bash
#
# SIGULA — Pemasangan aplikasi pertama kali
#
# Membuat database, clone repo, .env production, migrate, seed akun,
# izin folder, virtual host Nginx, dan cron scheduler (backup harian).
#
# Pemakaian (sebagai root, setelah setup-server.sh):
#   sudo bash setup-app.sh --domain api.nirasarimurni.com \
#                          --repo https://github.com/user/repo.git \
#                          --frontend-url https://nirasarimurni.com
#
# Opsi:
#   --domain        Nama domain/subdomain API (wajib). Boleh diisi IP untuk uji coba.
#   --repo          URL git repository (wajib pada pemasangan pertama).
#   --frontend-url  Origin frontend untuk CORS (boleh dipisah koma).
#   --branch        Branch git (default: main)
#   --subdir        Lokasi aplikasi Laravel di dalam repo (default: backend)
#   --db-name       Nama database (default: sigula)
#   --db-user       User database (default: sigula)
#   --php           Versi PHP (default: 8.3)

set -euo pipefail

DOMAIN=""; REPO=""; FRONTEND_URL=""; BRANCH="main"; SUBDIR="backend"
DB_NAME="sigula"; DB_USER="sigula"; PHP_VERSION="8.3"

while [ $# -gt 0 ]; do
    case "$1" in
        --domain)       DOMAIN="$2";       shift 2 ;;
        --repo)         REPO="$2";         shift 2 ;;
        --frontend-url) FRONTEND_URL="$2"; shift 2 ;;
        --branch)       BRANCH="$2";       shift 2 ;;
        --subdir)       SUBDIR="$2";       shift 2 ;;
        --db-name)      DB_NAME="$2";      shift 2 ;;
        --db-user)      DB_USER="$2";      shift 2 ;;
        --php)          PHP_VERSION="$2";  shift 2 ;;
        *) echo "Opsi tidak dikenal: $1" >&2; exit 1 ;;
    esac
done

info()  { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
ok()    { printf '\033[0;32m    OK  %s\033[0m\n' "$*"; }
warn()  { printf '\033[0;33m    !!  %s\033[0m\n' "$*"; }
fatal() { printf '\n\033[0;31mGAGAL: %s\033[0m\n' "$*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || fatal "Jalankan sebagai root: sudo bash $0 ..."
[ -n "$DOMAIN" ]     || fatal "--domain wajib diisi"

BASE_DIR="/var/www/${DOMAIN}"
APP_DIR="${BASE_DIR}/${SUBDIR}"
SOCKET="/run/php/php${PHP_VERSION}-fpm.sock"

command -v php      >/dev/null || fatal "PHP belum terpasang. Jalankan setup-server.sh dulu."
command -v composer >/dev/null || fatal "Composer belum terpasang. Jalankan setup-server.sh dulu."
[ -S "$SOCKET" ]               || fatal "Socket PHP-FPM tidak ada di $SOCKET (cek --php)"

# ---------------------------------------------------------------------------
info "Menyiapkan kode aplikasi di $APP_DIR"
# ---------------------------------------------------------------------------
if [ -d "$BASE_DIR/.git" ]; then
    ok "Repo sudah ada, mengambil update terbaru"
    git -C "$BASE_DIR" fetch --all --prune
    git -C "$BASE_DIR" checkout "$BRANCH"
    git -C "$BASE_DIR" pull --ff-only origin "$BRANCH"
else
    [ -n "$REPO" ] || fatal "--repo wajib diisi pada pemasangan pertama"
    mkdir -p /var/www
    git clone --branch "$BRANCH" "$REPO" "$BASE_DIR"
    ok "Repo di-clone"
fi

[ -f "$APP_DIR/artisan" ] || fatal "File artisan tidak ditemukan di $APP_DIR (cek --subdir)"

# ---------------------------------------------------------------------------
info "Membuat database MySQL"
# ---------------------------------------------------------------------------
DB_PASS="$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-24)"

# MySQL 8 memisahkan CREATE USER dan GRANT (sintaks lama
# "GRANT ... IDENTIFIED BY" pada panduan lama sudah tidak berlaku).
mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
ok "Database '${DB_NAME}' dan user '${DB_USER}' siap"

# ---------------------------------------------------------------------------
info "Memasang dependency PHP (tanpa dev)"
# ---------------------------------------------------------------------------
cd "$APP_DIR"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction
ok "Vendor terpasang"

# ---------------------------------------------------------------------------
info "Menulis konfigurasi .env production"
# ---------------------------------------------------------------------------
OWNER_PASS="$(openssl rand -base64 18 | tr -d '/+=' | cut -c1-16)"

if [ -f "$APP_DIR/.env" ]; then
    cp "$APP_DIR/.env" "$APP_DIR/.env.backup.$(date +%Y%m%d%H%M%S)"
    warn ".env lama dicadangkan, tidak ditimpa. Periksa manual bila perlu."
else
    cat > "$APP_DIR/.env" <<ENV
APP_NAME=SIGULA
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://${DOMAIN}

APP_LOCALE=id
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=id_ID
APP_TIMEZONE=Asia/Jakarta

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASS}

FRONTEND_URL=${FRONTEND_URL:-https://${DOMAIN}}

SIGULA_DEFAULT_PASSWORD=${OWNER_PASS}
SIGULA_SEED_DEMO=false

SESSION_DRIVER=database
SESSION_LIFETIME=120
BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=log
MAIL_FROM_ADDRESS="no-reply@${DOMAIN}"
MAIL_FROM_NAME="\${APP_NAME}"
ENV
    ok ".env production dibuat"
fi

php artisan key:generate --force --no-interaction
ok "APP_KEY dibuat"

# ---------------------------------------------------------------------------
info "Menjalankan migrasi dan mengisi data awal"
# ---------------------------------------------------------------------------
php artisan migrate --force --no-interaction

# Seeder dijalankan SEBELUM config:cache, dan hanya akun + master data
# (SIGULA_SEED_DEMO=false membuat data transaksi demo tidak ikut terpasang).
php artisan db:seed --class="Database\\Seeders\\UserSeeder"  --force --no-interaction
php artisan db:seed --class="Database\\Seeders\\MasterSeeder" --force --no-interaction
ok "Migrasi + akun + master data (harga, tarif, karyawan, eksportir) siap"

# ---------------------------------------------------------------------------
info "Mengatur izin folder"
# ---------------------------------------------------------------------------
chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
find "$APP_DIR/storage" -type d -exec chmod 775 {} \;
find "$APP_DIR/storage" -type f -exec chmod 664 {} \;
chmod -R 775 "$APP_DIR/bootstrap/cache"
ok "storage/ dan bootstrap/cache/ milik www-data"

# ---------------------------------------------------------------------------
info "Mengoptimalkan aplikasi"
# ---------------------------------------------------------------------------
php artisan optimize        # config + route + event cache sekaligus
ok "Konfigurasi, route, dan event di-cache"

# ---------------------------------------------------------------------------
info "Membuat virtual host Nginx"
# ---------------------------------------------------------------------------
TEMPLATE="$APP_DIR/deploy/nginx-sigula.conf.template"
VHOST="/etc/nginx/sites-available/${DOMAIN}"

[ -f "$TEMPLATE" ] || fatal "Template Nginx tidak ditemukan: $TEMPLATE"

sed -e "s|__DOMAIN__|${DOMAIN}|g" \
    -e "s|__ROOT__|${APP_DIR}/public|g" \
    -e "s|__PHP_SOCKET__|${SOCKET}|g" \
    "$TEMPLATE" > "$VHOST"

# Perhatikan SPASI sebelum /etc/nginx/sites-enabled/ — panduan lama sering
# salah ketik di bagian ini sehingga symlink-nya gagal dibuat.
ln -sfn "$VHOST" "/etc/nginx/sites-enabled/${DOMAIN}"

# Nonaktifkan default vhost agar tidak menangkap request domain ini.
rm -f /etc/nginx/sites-enabled/default

nginx -t || fatal "Konfigurasi Nginx tidak valid"
systemctl reload nginx
ok "Virtual host aktif: $VHOST"

# ---------------------------------------------------------------------------
info "Memasang cron scheduler (backup DB harian 02:00)"
# ---------------------------------------------------------------------------
CRON_LINE="* * * * * cd ${APP_DIR} && php artisan schedule:run >> /dev/null 2>&1"
CURRENT="$(crontab -u www-data -l 2>/dev/null || true)"

if printf '%s\n' "$CURRENT" | grep -Fq "${APP_DIR} && php artisan schedule:run"; then
    ok "Cron sudah terpasang"
else
    printf '%s\n%s\n' "$CURRENT" "$CRON_LINE" | sed '/^$/d' | crontab -u www-data -
    ok "Cron scheduler dipasang untuk user www-data"
fi

# ---------------------------------------------------------------------------
info "Verifikasi"
# ---------------------------------------------------------------------------
HTTP_CODE="$(curl -s -o /dev/null -w '%{http_code}' -H 'Accept: application/json' "http://127.0.0.1/api/v1/auth/me" || echo 000)"
if [ "$HTTP_CODE" = "401" ]; then
    ok "API merespons dengan benar (401 Unauthenticated tanpa token)"
else
    warn "Endpoint /api/v1/auth/me mengembalikan HTTP $HTTP_CODE (diharapkan 401). Cek log Nginx & Laravel."
fi

cat <<EOF

    ============================================================
     PEMASANGAN SELESAI
    ============================================================

     Aplikasi   : ${APP_DIR}
     Base URL   : http://${DOMAIN}/api/v1
     Database   : ${DB_NAME}

     KREDENSIAL — CATAT SEKARANG, TIDAK DITAMPILKAN LAGI
     ------------------------------------------------------------
     DB user     : ${DB_USER}
     DB password : ${DB_PASS}

     Akun aplikasi (password sama untuk ketiganya):
       owner@nirasarimurni.com     (Owner)
       gudang@nirasarimurni.com    (Staff Gudang)
       produksi@nirasarimurni.com  (Staff Produksi)
     Password    : ${OWNER_PASS}
     ------------------------------------------------------------

     LANGKAH BERIKUTNYA

     1. Arahkan DNS A record ${DOMAIN} ke IP server ini.
     2. Pasang SSL (wajib bila frontend sudah HTTPS):
          sudo apt install certbot python3-certbot-nginx
          sudo certbot --nginx -d ${DOMAIN}
     3. Ganti password ketiga akun lewat aplikasi/tinker.
     4. Uji dari laptop:
          curl -X POST https://${DOMAIN}/api/v1/auth/login \\
            -H 'Content-Type: application/json' \\
            -d '{"email":"owner@nirasarimurni.com","password":"${OWNER_PASS}"}'

     Update kode berikutnya cukup jalankan:
          sudo bash ${APP_DIR}/deploy/deploy.sh

EOF
