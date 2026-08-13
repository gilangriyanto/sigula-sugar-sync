# Sigula Sweet Success

Prompt Lovable (Final) — SIGULA: Sistem Informasi Gula Terintegrasi

PT Nira Sari Murni

Copy-paste seluruh teks di bawah ini ke Lovable untuk project BARU.

Buatkan saya web app dashboard bergaya modern, bersih, dan profesional untuk sistem manajemen bisnis pengadaan gula bernama "SIGULA — Sistem Informasi Gula Terintegrasi" milik PT Nira Sari Murni. Sistem ini menghubungkan proses pembelian gula dari petani, produksi (per sesi tungku dengan 2 pekerja), penggajian karyawan, penjualan ke eksportir, dan laporan keuangan dalam satu platform.

Gunakan data dummy/mock yang realistis (minimal 10-15 baris data per tabel, termasuk minimal 20-30 data karyawan dan beberapa hari histori sesi tungku) supaya terlihat seperti aplikasi yang sudah benar-benar dipakai. Semua tombol, form, dan interaksi harus benar-benar berfungsi menggunakan state management (React state) — bukan sekadar tampilan statis. Setiap kali user menambah/edit/hapus data, tampilan harus langsung update dan saling terhubung antar halaman.

Gaya Desain

Tema warna: gold/amber (#9C6B1F) sebagai warna utama (merepresentasikan gula), dipadukan dengan coklat tua (#3B2A1A) untuk teks, cream (#FBF3E3) untuk background section, dan putih bersih untuk card/table.

Font modern dan mudah dibaca (contoh: Inter).

Layout: sidebar navigasi di kiri (bisa collapse), topbar berisi nama user login.

Card dengan shadow halus, rounded corners, spacing lega.

Icon relevan di setiap menu.

Chart menarik di Dashboard dan Keuangan (bar chart & line chart).

Semua tabel punya search, filter, dan sorting.

Empty state yang bagus untuk data kosong.

Responsive, tetap enak dilihat di tablet.

Toast notification setiap berhasil simpan/ubah/hapus data.

Badge kecil "DEMO" di pojok atas.

Struktur Halaman

1. Login Page

Sederhana: logo "SIGULA", form email & password, tombol "Masuk" langsung ke Dashboard (tanpa autentikasi asli).

2. Dashboard

Card statistik atas:

Total Stok Bahan Mentah (kg) — breakdown per grade (NS 1, NS 2, Kecap)

Total Stok Gula Kristal (kg) & Total Stok Gula Brondol (kg) — dua card terpisah

Total Produksi Hari Ini (kg, gabungan semua sesi tungku hari ini)

Estimasi Keuntungan Bulan Ini (Rp)

Di bawahnya:

Grafik tren penjualan 6 bulan terakhir (line chart)

Grafik bar perbandingan pembelian bahan vs penjualan produk per bulan

Tabel "Aktivitas Terbaru" (5 transaksi terakhir lintas modul)

Widget "Rendemen Rata-rata Bulan Ini" (progress circle, contoh 93%)

Info "Sesi Tungku Aktif Hari Ini" (jumlah tungku yang sedang berjalan)

3. Data Petani

Tabel: Nama, Status (Badge Member/Non-Member), Nomor Member (format "Petani 231", kosong jika non-member), Kontak, Total Transaksi, Aksi.

Tombol "+ Tambah Petani": modal form Nama, Status (dropdown — jika Member auto-generate nomor 3 digit, jika Non-Member sembunyikan field nomor), Kontak, Alamat.

Search by nama/nomor member.

4. Master Harga & Tarif

3 tab dalam satu halaman:

Tab Harga Beli per Grade — Card untuk NS 1, NS 2, Kecap menampilkan harga saat ini per kg, tombol "Ubah Harga" (modal input harga baru + tanggal berlaku). Tampilkan riwayat perubahan harga per grade (tabel kecil: tanggal, harga lama, harga baru).

Tab Tarif Produksi — 2 card terpisah, masing-masing bisa diedit:

Tarif Gaji per Kg Gula Kristal (default Rp 1.150)

Tarif Gaji per Kg Gula Brondol (default Rp 800)

Uang Makan Harian (default Rp 5.000, dibayarkan per hari karyawan tercatat kerja)

Tab Data Karyawan & Eksportir — sub-tabel sederhana untuk kelola data karyawan (nama, kontak) dan data eksportir (nama perusahaan, kontak).

5. Pembelian Bahan dari Petani

Tabel: Tanggal, Nama Petani, Grade, Kilogram, Harga/kg, Total Bayar, Status (Lunas), Aksi.

Tombol "+ Transaksi Baru": modal — pilih petani (search), pilih grade, input kilogram, harga/kg auto-fill dari Master Harga (tapi tetap bisa diedit manual), total otomatis real-time (kg × harga). Tombol "Simpan & Cetak Kwitansi" dengan preview kwitansi printable.

Filter tanggal, petani, grade.

Card ringkasan: Total Pembelian Hari Ini, Total Pembelian Bulan Ini.

6. Manajemen Stok

Tab/section:

Stok Bahan Mentah — 3 card per grade dengan indikator warna (hijau aman, kuning menipis).

Stok Produk Jadi — 2 card terpisah: Gula Kristal & Gula Brondol.

Tabel Kartu Stok (histori keluar-masuk): Tanggal, Jenis (Masuk/Keluar), Kategori (Bahan Mentah NS1/NS2/Kecap, Produk Kristal, Produk Brondol), Jumlah, Keterangan.

Fitur Stok Opname sederhana (input koreksi manual + alasan).

7. Produksi (Sesi Tungku) — MODUL PALING PENTING, IKUTI LOGIC INI PERSIS

Konsep: Setiap sesi produksi terjadi di 1 tungku, selalu dikerjakan tepat 2 orang karyawan. Dalam 1 hari, banyak tungku bisa berjalan paralel (misal 15 tungku sekaligus). Brondol BUKAN produksi terpisah — dia hasil sampingan/reject yang keluar bersamaan dalam satu proses masak yang sama dengan Kristal.

Tabel daftar sesi tungku: Tanggal, Kode Tungku, Grade Bahan Mentah, Kg Bahan Mentah, 2 Nama Karyawan Bertugas, Kg Kristal, Kg Brondol, Rendemen (%), Status (Badge "Sedang Diproses" kuning / "Selesai" hijau), Aksi.

Tombol "+ Mulai Sesi Tungku Baru" — modal:

Tanggal

Pilih grade bahan mentah

Input kg bahan mentah

Pilih Karyawan 1 dan Karyawan 2 (wajib 2 slot, keduanya harus diisi, tidak boleh orang yang sama di 2 slot)

Simpan → status "Sedang Diproses"

Tombol "Selesaikan Sesi" pada baris berstatus "Sedang Diproses" — modal:

Input Kg Kristal Total (gabungan tungku itu, bukan per orang)

Input Kg Brondol Total (gabungan tungku itu, bukan per orang)

Sistem otomatis hitung & tampilkan: Rendemen = (Kg Kristal + Kg Brondol) ÷ Kg Bahan Mentah × 100%

Setelah disimpan:

Status berubah "Selesai"

Stok bahan mentah (grade terkait) berkurang sesuai kg bahan mentah tungku itu

Stok Kristal bertambah sesuai kg kristal total

Stok Brondol bertambah sesuai kg brondol total

Sistem otomatis membagi rata 2 kg bahan mentah, kg kristal, dan kg brondol ke kedua karyawan di tungku tsb, dan mencatatnya untuk keperluan penggajian di Modul 8 (contoh: kalau hasil 80kg Kristal + 20kg Brondol dari 100kg bahan mentah dikerjakan Asep & Pardi, maka masing-masing tercatat 50kg bahan mentah, 40kg Kristal, 10kg Brondol)

Tampilkan juga: grafik kecil "Tren Rendemen 14 Hari Terakhir", dan filter tanggal untuk melihat semua tungku yang jalan di 1 hari tertentu (karena banyak tungku paralel per hari).

8. Penggajian Karyawan

Periode gaji: Senin s.d. Jumat, dibayarkan setiap hari Jumat (bukan Senin-Minggu). Navigasi "Minggu Sebelumnya" / "Minggu Ini" / "Minggu Berikutnya" menampilkan rentang tanggal Senin-Jumat yang jelas.

Tabel per karyawan, kolom: Nama, Total Kg Kristal (minggu ini, terakumulasi dari semua sesi tungku), Total Kg Brondol (minggu ini), Hari Kerja (jumlah HARI BERBEDA karyawan tsb tercatat di sesi tungku manapun minggu itu — kalau ikut 3 sesi dalam 1 hari yang sama tetap dihitung 1 hari kerja), Upah Kristal (kg kristal × tarif kristal), Upah Brondol (kg brondol × tarif brondol), Uang Makan (hari kerja × tarif harian), Total Gaji, Status (Belum Dibayar/Sudah Dibayar), Aksi.

Tombol "Bayar" ubah status jadi Sudah Dibayar.

Tombol "Cetak Slip Gaji" menampilkan preview breakdown lengkap (semua komponen di atas, jangan cuma total).

Card ringkasan: Total Gaji Minggu Ini yang harus dibayarkan Jumat ini.

9. Penjualan ke Eksportir

Kristal dan Brondol punya harga jual berbeda-beda, jadi 1 transaksi berisi 2 baris terpisah (bukan 2 transaksi, tetap 1 invoice):

Modal "+ Transaksi Penjualan Baru":

Pilih Eksportir, Tanggal

Baris Kristal (opsional): Kilogram, Harga Jual/Kg, Subtotal (kg × harga, real-time). Tampilkan info "Stok Kristal Tersedia: xxx kg" sebagai validasi.

Baris Brondol (opsional): Kilogram, Harga Jual/Kg, Subtotal (kg × harga, real-time). Tampilkan info "Stok Brondol Tersedia: xxx kg".

Total Penjualan = Subtotal Kristal + Subtotal Brondol (otomatis dijumlah).

Minimal salah satu baris harus diisi.

Kalkulasi dua arah per baris (independen antara Kristal & Brondol):

Normalnya: isi Kilogram + Harga → Subtotal baris itu otomatis terhitung.

Kalau user klik dan edit langsung field Subtotal salah satu baris → Harga per Kg baris itu otomatis menyesuaikan (subtotal ÷ kilogram), sedangkan Kilogram baris itu TIDAK berubah. Baris satunya tidak ikut berubah.

Beri sedikit indikator visual (border halus/label kecil) saat harga baru saja ter-update otomatis, biar user paham.

Validasi: kilogram baris tsb tidak boleh 0 saat subtotal-nya sedang diedit manual.

Setelah simpan: stok Kristal & Brondol berkurang sesuai baris yang diisi, pemasukan tercatat di Keuangan, tombol "Cetak Invoice" menampilkan breakdown 2 baris + total. Tabel riwayat transaksi menampilkan breakdown kg & harga masing-masing jenis per transaksi, bukan cuma total gabungan. Card ringkasan: Total Penjualan Bulan Ini (Rp & kg, breakdown Kristal vs Brondol).

10. Keuangan & Laporan Laba Rugi

Filter periode (Bulan Ini/Bulan Lalu/Custom Range).

Card ringkasan besar: Pendapatan, Total HPP (pembelian bahan + total gaji karyawan termasuk uang makan), Biaya Operasional Lain, Laba Bersih (hijau jika untung).

Tabel input Biaya Operasional Lain-lain: Tanggal, Keterangan, Kategori (Listrik/Transport/Sewa/Lainnya), Jumlah, tombol "+ Tambah Biaya".

Grafik batang "Pendapatan vs Biaya per Bulan" (6 bulan terakhir).

Grafik line "Tren Margin Keuntungan (%) per Bulan".

Tombol "Export Laporan" (tampilan saja).

Detail Teknis Penting

Format Rupiah dengan pemisah ribuan (Rp 1.150.000). Format tanggal Indonesia (8 Agustus 2026).

Semua data harus konsisten dan saling terhubung antar halaman: pembelian → stok bahan mentah; sesi tungku selesai → stok bahan mentah berkurang, stok Kristal & Brondol bertambah, data karyawan ter-update untuk penggajian; penjualan → stok Kristal/Brondol berkurang, pemasukan masuk ke Keuangan.

Setiap modal form ada validasi dasar (tidak boleh kosong, angka tidak boleh negatif).

Sidebar menu urutan: Dashboard, Data Petani, Master Harga & Tarif, Pembelian Bahan, Manajemen Stok, Produksi (Sesi Tungku), Penggajian, Penjualan, Keuangan.

Tolong pastikan seluruh tombol dan interaksi benar-benar berfungsi (bukan dummy/dead button), terutama logic pembagian otomatis 2 karyawan per tungku dan kalkulasi dua arah di form penjualan — ini dua bagian paling krusial yang akan langsung dicek oleh calon klien. Tampilan modern dan profesional layaknya SaaS dashboard kelas menengah-atas, mudah dipahami meskipun pertama kali dilihat.

This project was built with [Lovable](https://lovable.dev).

**Live app**: https://sigula-sugar-sync.lovable.app

## Build with Lovable

Continue developing this project in the [Lovable editor](https://lovable.dev/projects/acfd5ecf-fd67-4e7e-bef0-eab6c186909d).

- **Ship faster**: describe what you want to build and Lovable handles the code.
- **Stay in sync**: every change made in Lovable is committed straight to this repository.
- **Full ownership**: this code is yours. Push to `main` on GitHub and your changes sync back into Lovable, ready for your next prompt.

## Development

Prefer working locally? You need Node.js and npm — [install with nvm](https://github.com/nvm-sh/nvm#installing-and-updating).

```sh
git clone <this-repository-url>
cd <repository-name>
npm i
npm run dev
```
