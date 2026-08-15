#!/usr/bin/env bash
#
# SIGULA — Provisioning server (sekali jalan per VPS)
#
# Memasang: firewall UFW, Nginx, MySQL, PHP 8.3-FPM + ekstensi, Composer, Git.
# Aman dijalankan ulang (idempoten).
#
# Pemakaian (sebagai root):
#   sudo bash setup-server.sh
#
# CATATAN PENTING
# Panduan BWAStore memakai PHP 7.2. Backend SIGULA berbasis Laravel 13 yang
# mewajibkan PHP >= 8.3, jadi versi PHP di sini sengaja dinaikkan.

set -euo pipefail

PHP_VERSION="${PHP_VERSION:-8.3}"

info()  { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
ok()    { printf '\033[0;32m    OK  %s\033[0m\n' "$*"; }
warn()  { printf '\033[0;33m    !!  %s\033[0m\n' "$*"; }
fatal() { printf '\n\033[0;31mGAGAL: %s\033[0m\n' "$*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || fatal "Jalankan sebagai root: sudo bash $0"

# ---------------------------------------------------------------------------
info "Memeriksa sistem operasi"
# ---------------------------------------------------------------------------
if [ -r /etc/os-release ]; then
    . /etc/os-release
    ok "$PRETTY_NAME"
    case "${VERSION_ID:-}" in
        22.04|24.04) ;;
        18.04|20.04) warn "Ubuntu $VERSION_ID sudah EOL. Disarankan pakai 22.04/24.04." ;;
        *)           warn "Versi $ID ${VERSION_ID:-?} belum diuji. Lanjut dengan risiko sendiri." ;;
    esac
else
    warn "Tidak bisa mendeteksi OS, melanjutkan."
fi

export DEBIAN_FRONTEND=noninteractive

# ---------------------------------------------------------------------------
info "Memperbarui paket sistem"
# ---------------------------------------------------------------------------
apt-get update -y
apt-get upgrade -y
ok "Paket sistem terbaru"

# ---------------------------------------------------------------------------
info "Mengatur firewall (UFW)"
# ---------------------------------------------------------------------------
apt-get install -y ufw
ufw allow OpenSSH             >/dev/null
ufw allow 'Nginx Full'        >/dev/null 2>&1 || true   # 80 + 443
ufw --force enable            >/dev/null
ok "SSH, HTTP, dan HTTPS diizinkan"
ufw status | sed 's/^/    /'

# ---------------------------------------------------------------------------
info "Memasang Nginx"
# ---------------------------------------------------------------------------
apt-get install -y nginx
systemctl enable --now nginx
ok "Nginx $(nginx -v 2>&1 | sed 's/.*\///')"

# ---------------------------------------------------------------------------
info "Memasang PHP $PHP_VERSION dan ekstensi yang dibutuhkan Laravel 13"
# ---------------------------------------------------------------------------
apt-get install -y software-properties-common ca-certificates lsb-release apt-transport-https
if ! grep -rq "ondrej/php" /etc/apt/sources.list.d/ 2>/dev/null; then
    add-apt-repository -y ppa:ondrej/php
    apt-get update -y
fi

apt-get install -y \
    "php${PHP_VERSION}-fpm" \
    "php${PHP_VERSION}-cli" \
    "php${PHP_VERSION}-mysql" \
    "php${PHP_VERSION}-mbstring" \
    "php${PHP_VERSION}-xml" \
    "php${PHP_VERSION}-bcmath" \
    "php${PHP_VERSION}-curl" \
    "php${PHP_VERSION}-zip" \
    "php${PHP_VERSION}-intl" \
    "php${PHP_VERSION}-gd" \
    "php${PHP_VERSION}-opcache"

systemctl enable --now "php${PHP_VERSION}-fpm"
ok "$(php -v | head -1)"

# Socket ini dipakai di konfigurasi Nginx — pastikan benar-benar ada.
SOCKET="/run/php/php${PHP_VERSION}-fpm.sock"
[ -S "$SOCKET" ] || fatal "Socket PHP-FPM tidak ditemukan di $SOCKET"
ok "Socket PHP-FPM: $SOCKET"

# Sedikit penyesuaian php.ini untuk API
PHP_INI="/etc/php/${PHP_VERSION}/fpm/php.ini"
if [ -f "$PHP_INI" ]; then
    sed -i 's/^;\?upload_max_filesize.*/upload_max_filesize = 20M/'  "$PHP_INI"
    sed -i 's/^;\?post_max_size.*/post_max_size = 20M/'             "$PHP_INI"
    sed -i 's/^;\?memory_limit.*/memory_limit = 256M/'              "$PHP_INI"
    sed -i 's/^;\?max_execution_time.*/max_execution_time = 60/'    "$PHP_INI"
    sed -i 's/^;\?cgi.fix_pathinfo.*/cgi.fix_pathinfo = 0/'         "$PHP_INI"
    systemctl restart "php${PHP_VERSION}-fpm"
    ok "php.ini disesuaikan (upload 20M, memory 256M, fix_pathinfo=0)"
fi

# ---------------------------------------------------------------------------
info "Memasang MySQL Server"
# ---------------------------------------------------------------------------
apt-get install -y mysql-server
systemctl enable --now mysql
ok "$(mysql --version)"
warn "Jalankan 'sudo mysql_secure_installation' setelah skrip ini bila server terbuka ke publik."

# ---------------------------------------------------------------------------
info "Memasang Composer"
# ---------------------------------------------------------------------------
apt-get install -y git unzip curl
if command -v composer >/dev/null 2>&1; then
    ok "Composer sudah terpasang: $(composer --version)"
else
    EXPECTED="$(curl -sS https://composer.github.io/installer.sig)"
    php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"
    ACTUAL="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"

    [ "$EXPECTED" = "$ACTUAL" ] || { rm -f /tmp/composer-setup.php; fatal "Installer Composer korup (hash tidak cocok)"; }

    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
    ok "$(composer --version)"
fi

# ---------------------------------------------------------------------------
info "Selesai"
# ---------------------------------------------------------------------------
cat <<EOF

    Server siap. Ringkasan:

      Nginx     : $(nginx -v 2>&1 | sed 's/.*\///')
      PHP       : $(php -r 'echo PHP_VERSION;')  (socket: $SOCKET)
      MySQL     : $(mysql --version | awk '{print $3}')
      Composer  : $(composer --version --no-ansi 2>/dev/null | awk '{print $3}')

    Langkah berikutnya — pasang aplikasinya:

      sudo bash setup-app.sh \\
        --domain api.nirasarimurni.com \\
        --repo   https://github.com/gilangriyanto/sigula-sugar-sync.git \\
        --frontend-url https://nirasarimurni.com

EOF
