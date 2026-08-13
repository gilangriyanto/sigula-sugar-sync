export type Grade = "NS 1" | "NS 2" | "Kecap";
export const GRADES: Grade[] = ["NS 1", "NS 2", "Kecap"];

export type StokKategori = Grade | "Kristal" | "Brondol";
export const KATEGORI_STOK: StokKategori[] = ["NS 1", "NS 2", "Kecap", "Kristal", "Brondol"];

export interface Petani {
  id: string;
  nama: string;
  status: "Member" | "Non-Member";
  nomorMember: string;
  kontak: string;
  alamat: string;
}

export interface Karyawan {
  id: string;
  nama: string;
  kontak: string;
}

export interface Eksportir {
  id: string;
  nama: string;
  kontak: string;
}

export interface RiwayatHarga {
  id: string;
  grade: Grade;
  tanggal: string;
  hargaLama: number;
  hargaBaru: number;
}

export interface Tarif {
  kristal: number;
  brondol: number;
  uangMakan: number;
}

export interface Pembelian {
  id: string;
  tanggal: string;
  petaniId: string;
  grade: Grade;
  kg: number;
  harga: number;
  total: number;
}

export interface StokMove {
  id: string;
  tanggal: string;
  jenis: "Masuk" | "Keluar";
  kategori: StokKategori;
  jumlah: number;
  keterangan: string;
}

export interface SesiTungku {
  id: string;
  tanggal: string;
  kodeTungku: string;
  grade: Grade;
  kgBahan: number;
  karyawanIds: [string, string];
  kgKristal: number | null;
  kgBrondol: number | null;
  status: "Sedang Diproses" | "Selesai";
}

export interface BarisJual {
  kg: number;
  harga: number;
}

export interface Penjualan {
  id: string;
  noInvoice: string;
  tanggal: string;
  eksportirId: string;
  kristal: BarisJual | null;
  brondol: BarisJual | null;
  total: number;
}

export type KategoriBiaya = "Listrik" | "Transport" | "Sewa" | "Lainnya";

export interface Biaya {
  id: string;
  tanggal: string;
  keterangan: string;
  kategori: KategoriBiaya;
  jumlah: number;
}

export interface BarisGaji {
  karyawanId: string;
  nama: string;
  kgKristal: number;
  kgBrondol: number;
  hariKerja: number;
  upahKristal: number;
  upahBrondol: number;
  uangMakan: number;
  total: number;
  dibayar: boolean;
}

export interface SigulaState {
  petani: Petani[];
  karyawan: Karyawan[];
  eksportir: Eksportir[];
  hargaBeli: Record<Grade, number>;
  riwayatHarga: RiwayatHarga[];
  tarif: Tarif;
  pembelian: Pembelian[];
  stokMoves: StokMove[];
  sesi: SesiTungku[];
  penjualan: Penjualan[];
  biaya: Biaya[];
  gajiDibayar: string[]; // `${karyawanId}|${mondayISO}`
}
