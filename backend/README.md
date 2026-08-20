# SIGULA — Backend API

Backend REST API untuk **SIGULA (Sistem Informasi Gula Terintegrasi)** milik PT Nira Sari Murni.
Menggantikan state lokal (mock) pada prototype frontend dengan data transaksi yang tersimpan
permanen, konsisten antar modul, dan bisa diaudit.

|           |                                                  |
| --------- | ------------------------------------------------ |
| Framework | Laravel 13 (PHP 8.3+)                            |
| Auth      | Laravel Sanctum (Bearer token), role-based       |
| Database  | MySQL/MariaDB (production) · SQLite (dev & test) |
| Base URL  | `/api/v1`                                        |
| Test      | 80 feature test, 418 assertion                   |

Dokumentasi lain:

- [`docs/API.md`](docs/API.md) — daftar endpoint, payload, dan contoh response
- [`docs/INTEGRASI-FRONTEND.md`](docs/INTEGRASI-FRONTEND.md) — cara menyambungkan React yang sudah ada
- [`docs/DEPLOY-VPS.md`](docs/DEPLOY-VPS.md) — panduan deploy ke VPS (Ubuntu + Nginx + MySQL + PHP 8.3)
- [`docs/SIGULA.postman_collection.json`](docs/SIGULA.postman_collection.json) — koleksi Postman siap import (50 request)

### Import ke Postman

1. **Import** → pilih `docs/SIGULA.postman_collection.json` dan
   `docs/SIGULA.postman_environment.json` (ada juga varian `-production`).
2. Pilih environment **SIGULA — Lokal** di pojok kanan atas.
3. Jalankan **1. Autentikasi → Login**. Token otomatis tersimpan ke variable koleksi,
   jadi 49 request lainnya langsung terautentikasi tanpa copy-paste token.

Request bertanda ⭐ menyimpan id hasilnya (`petaniId`, `sesiId`, `penjualanId`, …) sehingga
seluruh koleksi bisa dijalankan berurutan lewat **Collection Runner** sebagai smoke test —
Logout sengaja ditaruh di folder terakhir supaya tidak mencabut token di tengah jalan.
Variable `{{tanggalHariIni}}` diisi otomatis, jadi contoh body selalu memakai tanggal hari ini.

---

## 1. Menjalankan di lokal

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Isi kredensial database di `.env`, buat databasenya, lalu:

```bash
php artisan migrate --seed
php artisan serve            # http://localhost:8000
```

Ingin coba cepat tanpa MySQL? Set `DB_CONNECTION=sqlite` di `.env` lalu
`touch database/database.sqlite` sebelum `migrate --seed`.

### Akun bawaan seeder

| Email                        | Role           | Akses                         |
| ---------------------------- | -------------- | ----------------------------- |
| `owner@nirasarimurni.com`    | Owner          | Semua modul termasuk Keuangan |
| `gudang@nirasarimurni.com`   | Staff Gudang   | Petani, Pembelian, Stok       |
| `produksi@nirasarimurni.com` | Staff Produksi | Produksi (sesi tungku)        |

Password awal diambil dari `SIGULA_DEFAULT_PASSWORD` (default `password`).
**Ganti sebelum dipakai di server.**

### Data demo

`SIGULA_SEED_DEMO=true` mengisi ±6 bulan transaksi (±1.800 sesi tungku, 600 pembelian,
39 invoice penjualan, gaji 2 minggu sudah dibayar) memakai service yang sama dengan API,
jadi saldo stok dan laporan dijamin konsisten. Set `false` di production.

---

## 2. Perintah harian

```bash
php artisan test                  # seluruh test suite
php artisan migrate:fresh --seed  # reset + isi ulang data demo
php artisan sigula:backup-db      # backup manual (otomatis tiap 02:00 lewat scheduler)
./vendor/bin/pint                 # format kode
```

---

## 3. Arsitektur

```
app/
├── Enums/          Grade, KategoriStok, StatusSesi, JenisTarif, Role, ...
├── Models/         Eloquent + casts + scope
├── Services/       SELURUH logika bisnis ada di sini
│   ├── StokService          satu-satunya pintu perubahan stok
│   ├── PembelianService     pembelian + efek stok
│   ├── ProduksiService      sesi tungku, rendemen, pembagian 2 karyawan
│   ├── PenjualanService     invoice 2 baris + efek stok
│   ├── PenggajianService    rekap mingguan Senin-Jumat + pembayaran
│   ├── HargaService         master harga berversi
│   ├── TarifService         master tarif berversi
│   ├── LaporanService       laba rugi & tren bulanan
│   └── DashboardService     ringkasan operasional
├── Http/
│   ├── Requests/   validasi input (payload camelCase, sama dengan frontend)
│   ├── Resources/  bentuk response
│   └── Controllers/Api/V1
└── Support/        Periode (Senin-Jumat), TarifResolver
```

Controller sengaja dibuat tipis: memvalidasi, memanggil service, membungkus response.
Semua aturan bisnis dan efek samping ada di service supaya bisa dipakai ulang oleh
seeder, command, dan test tanpa lewat HTTP.

### Prinsip yang dipegang

**Stok tidak pernah dihitung ulang dari nol.**
`stok_saldo` menyimpan saldo berjalan per kategori dan dikunci (`lockForUpdate`) setiap
kali berubah; `kartu_stok` adalah log append-only lengkap dengan `saldo_setelah`.
Pembatalan transaksi dicatat sebagai mutasi balik, bukan penghapusan baris — audit trail utuh.

**Harga & tarif berversi.**
Perubahan harga/tarif selalu INSERT baris baru dengan `berlaku_dari`. Pembelian menyimpan
`grade_harga_id` yang dipakai saat itu, dan penggajian memakai tarif yang berlaku pada
**tanggal produksi**, bukan tarif terbaru.

**Satu tungku = satu sesi, tepat 2 karyawan.**
Brondol bukan produksi terpisah — ia keluar bersamaan dengan kristal dalam sesi yang sama.
Saat sesi diselesaikan, bahan mentah dan hasil dibagi rata ke kedua karyawan dan disimpan
di `produksi_karyawan` sebagai satu-satunya sumber data penggajian.

**Semua perubahan saldo dibungkus database transaction.**
`StokService` bahkan menolak dipanggil di luar transaction (`LogicException`), jadi tidak
mungkin ada mutasi setengah jadi.

---

## 4. Aturan bisnis yang dijaga sistem

| Aturan             | Perilaku                                                                                  |
| ------------------ | ----------------------------------------------------------------------------------------- |
| Rendemen           | `(kg kristal + kg brondol) ÷ kg bahan mentah × 100%`, dihitung otomatis                   |
| Pembagian karyawan | Otomatis ÷2; porsi kedua = sisa, jadi jumlah 2 porsi selalu persis sama dengan total sesi |
| Hari kerja         | Jumlah **tanggal berbeda**; 3 sesi dalam 1 hari tetap 1 hari kerja                        |
| Periode gaji       | Senin s.d. **Jumat**, dibayarkan Jumat                                                    |
| Gaji dibayar       | Angka dibekukan (snapshot) di `gaji_mingguan`, tidak berubah lagi                         |
| Sesi vs gaji       | Sesi tidak bisa dibatalkan bila gaji periodenya sudah dibayar                             |
| Hasil produksi     | Tidak boleh melebihi bahan mentah (kekekalan massa)                                       |
| Mulai sesi         | Bahan yang sedang dipakai tungku lain yang belum selesai tidak dihitung tersedia          |
| Penjualan          | 1 invoice, maksimal 2 baris (kristal & brondol) dengan kg + harga masing-masing           |
| Stok minus         | Tidak pernah bisa terjadi — mutasi ditolak sebelum saldo tersimpan                        |
| Nomor dokumen      | Counter dikunci di database, tidak mungkin kembar walau request bersamaan                 |
| Hapus master       | Petani/eksportir bertransaksi tidak bisa dihapus; karyawan berhistori dinonaktifkan       |

---

## 5. Hak akses

| Modul                | Owner |       Staff Gudang        |      Staff Produksi       |
| -------------------- | :---: | :-----------------------: | :-----------------------: |
| Dashboard            |  ✅   | ✅ (tanpa angka keuangan) | ✅ (tanpa angka keuangan) |
| Data Petani          |  ✅   |            ✅             |             —             |
| Master Harga & Tarif |  ✅   |           lihat           |           lihat           |
| Pembelian            |  ✅   |            ✅             |             —             |
| Stok & Opname        |  ✅   |            ✅             |           lihat           |
| Produksi             |  ✅   |           lihat           |            ✅             |
| Penggajian           |  ✅   |             —             |             —             |
| Penjualan            |  ✅   |             —             |             —             |
| Keuangan & Audit Log |  ✅   |             —             |             —             |

Diimplementasikan sebagai Gate (`App\Providers\AuthServiceProvider`) dan dipasang di route
lewat middleware `can:`. Endpoint `GET /auth/me` mengembalikan `menu` dan `abilities`
sehingga frontend cukup memakainya untuk menyusun sidebar.

---

## 6. Deployment (VPS)

Panduan lengkap: [`docs/DEPLOY-VPS.md`](docs/DEPLOY-VPS.md). Versi singkatnya —
dua skrip di [`deploy/`](deploy/) mengerjakan seluruh proses:

```bash
sudo bash deploy/setup-server.sh          # Nginx + MySQL + PHP 8.3 + Composer
sudo bash deploy/setup-app.sh \
  --domain api.nirasarimurni.com \
  --repo https://github.com/<user>/<repo>.git \
  --frontend-url https://nirasarimurni.com
```

Update kode berikutnya:

```bash
sudo bash deploy/deploy.sh                # maintenance -> pull -> migrate -> cache -> live
```

> **Wajib PHP ≥ 8.3.** Panduan deployment lama yang memakai PHP 7.2 tidak akan
> bisa menjalankan Laravel 13 sama sekali.

`.env` production minimal:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.nirasarimurni.com
FRONTEND_URL=https://nirasarimurni.com
SIGULA_SEED_DEMO=false
SIGULA_DEFAULT_PASSWORD=<password-kuat>
```

Satu baris cron mengaktifkan backup harian (02:00, disimpan 14 hari) dan pembersihan token:

```cron
* * * * * cd /var/www/sigula/backend && php artisan schedule:run >> /dev/null 2>&1
```

Kedua nilai `SIGULA_*` dibaca lewat [`config/sigula.php`](config/sigula.php), bukan
`env()` langsung, supaya tetap benar setelah `php artisan optimize` dijalankan
(setelah config di-cache, `env()` di luar file config mengembalikan null).

---

## 7. Catatan teknis

- **Format JSON camelCase** (`kgBahan`, `noInvoice`, `nomorMember`) mengikuti tipe data
  yang sudah dipakai frontend; kolom database tetap snake_case.
- **ID dikirim sebagai string** agar cocok dengan tipe `id: string` di frontend.
- **Angka dikirim sebagai number**, pemformatan Rupiah tetap di frontend
  (`tanggalLabel` disediakan untuk tanggal gaya Indonesia).
- **Enum menerima dua bentuk**: `"NS 1"` maupun `"ns1"`, `"Sedang Diproses"` maupun
  `"sedang_diproses"`. Response mengirim label untuk tampilan plus `*Kode` untuk logika.
- **Pesan error berbahasa Indonesia**, format sama untuk error validasi dan pelanggaran
  aturan bisnis: `{ "message": "...", "errors": { "field": ["..."] } }` dengan status 422.
