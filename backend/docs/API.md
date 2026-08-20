# SIGULA API v1 — Referensi Endpoint

> Ingin langsung mencoba? Import [`SIGULA.postman_collection.json`](SIGULA.postman_collection.json)
>
> - [`SIGULA.postman_environment.json`](SIGULA.postman_environment.json) ke Postman —
>   seluruh 50 endpoint di dokumen ini sudah tersedia sebagai request siap jalan, lengkap
>   dengan auto-simpan token setelah Login.

Base URL: `{APP_URL}/api/v1`

Semua request/response memakai JSON dan header:

```
Accept: application/json
Content-Type: application/json
Authorization: Bearer <token>     // kecuali endpoint login
```

## Konvensi

**Response sukses**

```jsonc
// item tunggal
{ "data": { ... }, "message": "Transaksi pembelian tersimpan." }

// koleksi tanpa paginasi (master data)
{ "data": [ ... ] }

// koleksi berpaginasi
{ "data": [ ... ], "links": { ... }, "meta": { "current_page": 1, "total": 607, ... } }
```

**Response error** — selalu bentuk yang sama, baik error validasi maupun pelanggaran
aturan bisnis:

```jsonc
// 422
{
    "message": "Stok Kristal hanya 4.155,20 kg, tidak cukup untuk menjual 900.000 kg.",
    "errors": { "kristal.kg": ["Stok Kristal hanya 4.155,20 kg, ..."] },
}
```

| Status    | Arti                                        |
| --------- | ------------------------------------------- |
| 200 / 201 | Berhasil                                    |
| 401       | Token tidak ada / tidak valid               |
| 403       | Role tidak punya akses ke modul tersebut    |
| 404       | Data tidak ditemukan                        |
| 422       | Validasi gagal atau aturan bisnis dilanggar |
| 429       | Rate limit login (10 percobaan/menit)       |

**Nilai enum** — request menerima label maupun kode; response mengirim keduanya.

| Konsep         | Label (untuk tampilan)                    | Kode (untuk logika)          |
| -------------- | ----------------------------------------- | ---------------------------- |
| Grade          | `NS 1`, `NS 2`, `Kecap`                   | `ns1`, `ns2`, `kecap`        |
| Kategori stok  | + `Kristal`, `Brondol`                    | + `kristal`, `brondol`       |
| Status sesi    | `Sedang Diproses`, `Selesai`              | `sedang_diproses`, `selesai` |
| Status bayar   | `Lunas`, `Belum Lunas`                    | `lunas`, `belum_lunas`       |
| Status petani  | `Member`, `Non-Member`                    | `member`, `non_member`       |
| Kategori biaya | `Listrik`, `Transport`, `Sewa`, `Lainnya` | huruf kecil                  |

**Paginasi** — `?perPage=25` (maks 200) dan `?page=2` pada endpoint transaksi.

---

## 1. Autentikasi

### `POST /auth/login`

```jsonc
// request
{ "email": "owner@nirasarimurni.com", "password": "password", "namaPerangkat": "sigula-web" }

// response 200
{ "message": "Berhasil masuk.",
  "data": {
    "token": "1|xxxxx",
    "user": { "id": "1", "nama": "Shoffal (Owner)", "email": "...", "role": "owner",
              "roleLabel": "Owner", "aktif": true,
              "menu": ["dashboard","petani","master","pembelian","stok","produksi","penggajian","penjualan","keuangan"],
              "abilities": ["lihat-dashboard","lihat-keuangan", "..."] } } }
```

### `GET /auth/me` · `POST /auth/logout`

`me` mengembalikan objek `user` yang sama. `logout` mencabut token yang sedang dipakai.

---

## 2. Dashboard

### `GET /dashboard`

Blok `keuangan` dan `tren` hanya muncul untuk role yang boleh melihat keuangan.

```jsonc
{
    "data": {
        "tanggal": "2026-08-13",
        "stok": {
            "bahanMentah": { "NS 1": 1886, "NS 2": 1978, "Kecap": 2014, "total": 5878 },
            "kristal": 4075.2,
            "brondol": 912.67,
        },
        "produksiHariIni": {
            "jumlahSesi": 14,
            "tungkuAktif": 4,
            "kgBahan": 1755,
            "kgKristal": 997.19,
            "kgBrondol": 204.18,
            "totalProduksi": 1201.37,
            "rendemen": 93.42,
        },
        "rendemenBulanIni": 93.26,
        "keuangan": {
            "pendapatanBulanIni": 322639265,
            "labaBulanIni": 103495883.5,
            "hppBulanIni": 206443381.5,
            "biayaOperasionalBulanIni": 12700000,
            "margin": 32.08,
        },
        "tren": [
            {
                "bulan": "2026-03",
                "label": "Mar 26",
                "pendapatan": 702481372,
                "pembelian": 394532100,
                "gaji": 40239062.5,
                "biayaOperasional": 12100000,
                "totalBiaya": 446871162.5,
                "laba": 255610209.5,
                "margin": 36.4,
            },
        ],
        "aktivitasTerbaru": [
            {
                "id": "beli-607",
                "tanggal": "2026-08-13",
                "modul": "Pembelian",
                "keterangan": "Sukirman — NS 1 350 kg",
                "nilai": "Rp 5.075.000",
            },
        ],
    },
}
```

---

## 3. Data Petani

| Method | Endpoint       | Keterangan                                |
| ------ | -------------- | ----------------------------------------- |
| GET    | `/petani`      | `?q=` cari nama / nomor member / kontak   |
| POST   | `/petani`      |                                           |
| GET    | `/petani/{id}` |                                           |
| PUT    | `/petani/{id}` |                                           |
| DELETE | `/petani/{id}` | Ditolak bila petani sudah punya transaksi |

```jsonc
// POST request — nomorMember boleh dikosongkan, sistem generate 3 digit berikutnya
{ "nama": "Sukirman", "status": "Member", "kontak": "0812-3344-5566", "alamat": "Desa Sukamaju" }

// response 201
{ "message": "Petani berhasil ditambahkan.",
  "data": { "id": "1", "nama": "Sukirman", "status": "Member", "statusKode": "member",
            "nomorMember": "201", "labelMember": "Petani 201", "kontak": "...", "alamat": "...",
            "totalTransaksi": 0, "totalNilai": 0 } }
```

Status `Non-Member` otomatis mengosongkan `nomorMember` walaupun dikirim.

---

## 4. Master Harga, Tarif, Karyawan, Eksportir

### `GET /master/harga`

```jsonc
{
    "data": {
        "hargaBeli": { "NS 1": 14500, "NS 2": 12750, "Kecap": 9500 },
        "grade": [
            { "kode": "ns1", "nama": "NS 1", "hargaPerKg": 14500, "berlakuDari": "2026-05-16" },
        ],
        "riwayat": [
            {
                "id": "7",
                "grade": "NS 1",
                "gradeKode": "ns1",
                "tanggal": "2026-05-16",
                "hargaLama": 13900,
                "hargaBaru": 14500,
                "catatan": "Penyesuaian harga pasar",
            },
        ],
    },
}
```

### `POST /master/harga`

```jsonc
{ "grade": "NS 1", "harga": 15200, "berlakuDari": "2026-08-13", "catatan": "Kenaikan harga pasar" }
```

Menambah versi harga baru — record lama tidak pernah diubah. `berlakuDari` opsional
(default: sekarang).

### `GET /master/tarif` · `POST /master/tarif`

```jsonc
// GET
{ "data": { "tarif": { "kristal": 1150, "brondol": 800, "uangMakan": 5000 },
            "jenis": [ { "kode": "kristal", "nama": "Tarif Gula Kristal per Kg", "nilai": 1150 } ],
            "riwayat": [ ... ] } }

// POST — jenis: kristal | brondol | uang_makan (boleh ditulis "uangMakan")
{ "jenis": "kristal", "nilai": 1250, "berlakuDari": "2026-08-13" }
```

### Karyawan & Eksportir

| Method     | Endpoint                                          |
| ---------- | ------------------------------------------------- |
| GET/POST   | `/master/karyawan` · `?q=`, `?sertakanNonaktif=1` |
| PUT/DELETE | `/master/karyawan/{id}`                           |
| GET/POST   | `/master/eksportir`                               |
| PUT/DELETE | `/master/eksportir/{id}`                          |

Menghapus karyawan yang pernah ikut sesi tungku akan **menonaktifkan**-nya (bukan
menghapus) agar histori gaji tetap bisa ditelusuri; response menjelaskan hal ini.

---

## 5. Pembelian Bahan

### `GET /pembelian`

Filter: `dari`, `sampai`, `petaniId`, `grade`, `q` (nomor kwitansi / nama petani).
Response menyertakan `ringkasan`:

```jsonc
{ "data": [ ... ], "meta": { ... },
  "ringkasan": { "hariIni": 5075000, "kgHariIni": 350, "bulanIni": 168420000, "kgBulanIni": 11620 } }
```

### `POST /pembelian`

```jsonc
// request — "harga" boleh dikosongkan: otomatis dari master harga yang berlaku
// pada tanggal transaksi, tapi tetap bisa dinego manual per transaksi
{ "tanggal": "2026-08-13", "petaniId": "1", "grade": "NS 1", "kg": 250, "harga": null }

// response 201
{ "message": "Transaksi pembelian tersimpan.",
  "data": { "id": "608", "nomorKwitansi": "KW/2026/08/0044", "tanggal": "2026-08-13",
            "tanggalLabel": "13 Agustus 2026", "petaniId": "1", "namaPetani": "Sukirman",
            "grade": "NS 1", "gradeKode": "ns1", "kg": 250, "harga": 14500, "total": 3625000,
            "statusPembayaran": "Lunas", "statusPembayaranKode": "lunas" },
  "kwitansi": { "nomor": "KW/2026/08/0044", "tanggal": "13 Agustus 2026",
                "namaPetani": "Sukirman", "nomorMember": "Petani 201", "grade": "NS 1",
                "kilogram": 250, "hargaPerKg": 14500, "total": 3625000,
                "statusPembayaran": "Lunas" } }
```

Efek otomatis: stok bahan mentah grade tersebut bertambah + 1 baris kartu stok.

### `DELETE /pembelian/{id}`

Membatalkan transaksi: stok dikeluarkan kembali lewat mutasi balik dan record di-soft
delete. Ditolak bila bahannya sudah terlanjur dipakai produksi (stok tidak cukup).

---

## 6. Manajemen Stok

### `GET /stok`

```jsonc
{
    "data": {
        "saldo": {
            "NS 1": 1886,
            "NS 2": 1978,
            "Kecap": 2014,
            "Kristal": 4075.2,
            "Brondol": 912.67,
        },
        "bahanMentah": [
            {
                "kode": "ns1",
                "nama": "NS 1",
                "label": "Bahan Mentah NS 1",
                "saldo": 1886,
                "status": "aman",
            },
        ],
        "totalBahanMentah": 5878,
        "produkJadi": [
            {
                "kode": "kristal",
                "nama": "Kristal",
                "label": "Produk Kristal",
                "saldo": 4075.2,
                "status": "aman",
            },
        ],
        "ambangMenipis": 1500,
    },
}
```

`status` bernilai `menipis` bila stok bahan mentah di bawah `ambangMenipis`.

### `GET /stok/kartu`

Filter: `kategori`, `jenis` (`masuk`/`keluar`), `dari`, `sampai`, `q` (keterangan).

```jsonc
{
    "data": [
        {
            "id": "4241",
            "tanggal": "2026-08-13",
            "tanggalLabel": "13 Agustus 2026",
            "jenis": "Keluar",
            "jenisKode": "keluar",
            "kategori": "NS 1",
            "kategoriKode": "ns1",
            "kategoriLabel": "Bahan Mentah NS 1",
            "jumlah": 100,
            "saldoSetelah": 2186,
            "keterangan": "Produksi tungku TGK-01",
            "referensiTipe": "sesi_tungku",
            "referensiId": "1837",
        },
    ],
}
```

### `POST /stok/opname`

```jsonc
{
    "kategori": "NS 1",
    "stokFisik": 1850,
    "alasan": "Hasil hitung fisik gudang",
    "tanggal": "2026-08-13",
}
```

Selisih dihitung otomatis terhadap saldo sistem dan dicatat sebagai mutasi biasa di kartu
stok (tidak ada jalur mengubah saldo diam-diam). Selisih nol ditolak.

---

## 7. Produksi (Sesi Tungku)

### `GET /produksi/sesi`

Filter: `tanggal` (semua tungku di hari itu), `dari`, `sampai`, `status`, `grade`,
`karyawanId`, `q` (kode tungku / nama karyawan). Menyertakan `ringkasan` harian.

### `POST /produksi/sesi` — mulai sesi

```jsonc
// request — kodeTungku opsional (otomatis TGK-01, TGK-02, ... per hari)
{ "tanggal": "2026-08-13", "kodeTungku": "TGK-01", "grade": "NS 1", "kgBahan": 100,
  "karyawan1Id": "2", "karyawan2Id": "1" }

// response 201
{ "message": "Sesi tungku TGK-01 dimulai.",
  "data": { "id": "1837", "tanggal": "2026-08-13", "kodeTungku": "TGK-01", "grade": "NS 1",
            "kgBahan": 100, "karyawanIds": ["2","1"],
            "karyawan": [ { "id": "2", "nama": "Pardi" }, { "id": "1", "nama": "Asep Saepudin" } ],
            "kgKristal": null, "kgBrondol": null, "rendemen": null,
            "status": "Sedang Diproses", "statusKode": "sedang_diproses" } }
```

Validasi: dua slot karyawan wajib diisi dan tidak boleh orang yang sama; bahan mentah
harus tersedia **setelah dikurangi tungku lain yang masih berjalan**.

### `POST /produksi/sesi/{id}/selesai`

```jsonc
// request — kg untuk SATU TUNGKU (gabungan 2 karyawan), bukan per orang
{ "kgKristal": 80, "kgBrondol": 20 }

// response 200
{ "message": "Sesi TGK-01 selesai. Rendemen 100%, masing-masing karyawan tercatat 40 kg kristal & 10 kg brondol.",
  "data": { "...": "...", "kgKristal": 80, "kgBrondol": 20, "rendemen": 100, "status": "Selesai" } }
```

Efek otomatis saat sesi ditutup:

1. Stok bahan mentah grade tersebut berkurang `kgBahan`
2. Stok Kristal bertambah `kgKristal`, stok Brondol bertambah `kgBrondol`
3. 3 baris kartu stok (1 keluar, 2 masuk)
4. 2 baris `produksi_karyawan` — bahan, kristal, dan brondol dibagi rata ÷2

Ditolak bila: sesi sudah selesai, total hasil 0, atau hasil melebihi bahan mentah.

### `GET /produksi/sesi/{id}`

Menyertakan `porsiKaryawan`:

```jsonc
[
    { "karyawanId": "1", "kgBahan": 50, "kgKristal": 40, "kgBrondol": 10 },
    { "karyawanId": "2", "kgBahan": 50, "kgKristal": 40, "kgBrondol": 10 },
]
```

### `DELETE /produksi/sesi/{id}`

Mengembalikan seluruh efek stok dan menghapus porsi karyawan. Ditolak bila gaji periode
tersebut sudah dibayarkan.

### `GET /produksi/tren-rendemen?hari=14`

```jsonc
{ "data": [{ "tanggal": "2026-08-13", "kgBahan": 1755, "kgHasil": 1201.37, "rendemen": 93.4 }] }
```

---

## 8. Penggajian

### `GET /penggajian?tanggal=2026-08-13`

`tanggal` boleh hari apa saja dalam minggu yang dimaksud — sistem mengunci ke periode
Senin–Jumat. Tanpa parameter berarti minggu berjalan.

```jsonc
{
    "data": {
        "periode": {
            "senin": "2026-08-10",
            "jumat": "2026-08-14",
            "label": "10 Agustus 2026 — 14 Agustus 2026",
        },
        "tarif": { "kristal": 1150, "brondol": 800, "uangMakan": 5000 },
        "baris": [
            {
                "karyawanId": "17",
                "nama": "Hendra Gunawan",
                "kgKristal": 242.18,
                "kgBrondol": 38.53,
                "hariKerja": 4,
                "upahKristal": 278507,
                "upahBrondol": 30824,
                "uangMakan": 20000,
                "total": 329331,
                "dibayar": false,
                "dibayarPada": null,
                "adaPerubahanSetelahDibayar": false,
            },
        ],
        "ringkasan": {
            "totalGaji": 5934030.5,
            "sudahDibayar": 0,
            "belumDibayar": 5934030.5,
            "jumlahKaryawan": 28,
        },
    },
}
```

- `hariKerja` = jumlah tanggal berbeda; ikut 3 sesi dalam sehari tetap 1 hari.
- Upah memakai tarif yang berlaku pada **tanggal produksi** masing-masing.
- `adaPerubahanSetelahDibayar` menandai baris yang sudah dibayar tapi datanya berubah.
- `?sertakanTanpaProduksi=1` ikut menampilkan karyawan tanpa produksi minggu itu.

### `GET /penggajian/slip/{karyawanId}?tanggal=`

Rincian satu karyawan (periode, tarif, semua komponen) untuk dicetak.

### `POST /penggajian/{karyawanId}/bayar` · `POST /penggajian/bayar-semua`

Body: `{ "tanggal": "2026-08-13" }` (opsional). Angka dibekukan sebagai snapshot.
Memanggil ulang untuk karyawan yang sudah dibayar aman (idempoten).

---

## 9. Penjualan ke Eksportir

### `GET /penjualan`

Filter: `dari`, `sampai`, `eksportirId`, `q`. Menyertakan `ringkasan` bulan berjalan
(`rupiah`, `kgKristal`, `kgBrondol`, `rupiahKristal`, `rupiahBrondol`, `jumlahTransaksi`).

### `POST /penjualan`

Satu invoice, dua baris opsional dengan harga masing-masing. Minimal satu baris diisi.

```jsonc
// request — kirim null / hilangkan baris yang tidak dijual
{ "tanggal": "2026-08-13", "eksportirId": "1",
  "kristal": { "kg": 2000, "harga": 24500 },
  "brondol": { "kg": 500, "harga": 16500 },
  "statusPembayaran": "lunas" }

// response 201
{ "message": "Transaksi penjualan tersimpan.",
  "data": { "id": "40", "noInvoice": "INV/2026/0040", "tanggal": "2026-08-13",
            "eksportirId": "1", "namaEksportir": "PT Global Sweet Export",
            "kristal": { "kg": 2000, "harga": 24500, "subtotal": 49000000 },
            "brondol": { "kg": 500, "harga": 16500, "subtotal": 8250000 },
            "total": 57250000, "statusPembayaran": "Lunas" },
  "invoice": { "nomor": "INV/2026/0040", "tanggal": "13 Agustus 2026",
               "eksportir": "PT Global Sweet Export",
               "baris": [ { "jenis": "Gula Kristal", "kilogram": 2000,
                            "hargaPerKg": 24500, "subtotal": 49000000 },
                          { "jenis": "Gula Brondol", "kilogram": 500,
                            "hargaPerKg": 16500, "subtotal": 8250000 } ],
               "total": 57250000, "statusPembayaran": "Lunas" } }
```

> **Kalkulasi dua arah** (subtotal diedit manual → harga per kg menyesuaikan, kilogram
> tetap) dikerjakan di frontend. Backend menerima `kg` + `harga` final, lalu menghitung
> ulang subtotal dan total sendiri sehingga angka yang tersimpan selalu konsisten.

Efek otomatis: stok Kristal/Brondol berkurang sesuai baris yang diisi, pemasukan masuk
ke laporan keuangan. Kekurangan stok ditolak dengan menyebut baris yang bermasalah
(`kristal.kg` / `brondol.kg`) dan **tidak menyisakan perubahan apa pun**.

### `PATCH /penjualan/{id}/status` · `DELETE /penjualan/{id}`

Ubah status pembayaran (`{ "statusPembayaran": "lunas" }`) atau batalkan transaksi
(stok dikembalikan).

---

## 10. Keuangan & Laporan

### `GET /keuangan/laba-rugi`

`?periode=bulan_ini` (default) · `bulan_lalu` · `custom&dari=YYYY-MM-DD&sampai=YYYY-MM-DD`

```jsonc
{
    "data": {
        "periode": { "dari": "2026-08-01", "sampai": "2026-08-31" },
        "pendapatan": 322639265,
        "hpp": {
            "bahan": 168420000,
            "gaji": {
                "upahKristal": 30110000,
                "upahBrondol": 4213381.5,
                "uangMakan": 3700000,
                "total": 38023381.5,
            },
            "total": 206443381.5,
        },
        "biayaOperasional": 12700000,
        "labaBersih": 103495883.5,
        "margin": 32.08,
    },
}
```

Rumus: `Pendapatan − (Pembelian bahan + Gaji termasuk uang makan) − Biaya operasional`.
Tidak ada angka hardcode; semuanya diturunkan dari transaksi.

### `GET /keuangan/tren?bulan=6`

Deret bulanan `{ bulan, label, pendapatan, pembelian, gaji, biayaOperasional, totalBiaya, laba, margin }`.

### Biaya Operasional

| Method | Endpoint                                                |
| ------ | ------------------------------------------------------- |
| GET    | `/keuangan/biaya` · filter `dari`, `sampai`, `kategori` |
| POST   | `/keuangan/biaya`                                       |
| PUT    | `/keuangan/biaya/{id}`                                  |
| DELETE | `/keuangan/biaya/{id}`                                  |

```jsonc
{
    "tanggal": "2026-08-12",
    "keterangan": "Tagihan listrik pabrik",
    "kategori": "Listrik",
    "jumlah": 2500000,
}
```

### `GET /audit-log`

Khusus Owner. Filter: `aksi` (prefix, mis. `harga.`), `userId`, `dari`, `sampai`.
Mencatat perubahan harga, tarif, transaksi pembelian/penjualan, produksi, stok opname,
dan pembayaran gaji — lengkap dengan pelaku, waktu, dan IP.
