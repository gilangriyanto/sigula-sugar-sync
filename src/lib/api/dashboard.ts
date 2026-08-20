import { apiClient } from "@/lib/api-client";

/**
 * Bentuk data persis seperti folder "2. Dashboard" di
 * SIGULA.postman_collection.json, cross-check ke DashboardController /
 * DashboardService.
 *
 * `keuangan` dan `tren` hanya dikirim backend untuk role yang punya ability
 * `lihat-keuangan` (Owner) — makanya opsional di sini, bukan hilang karena bug.
 */

export interface DashboardStok {
  bahanMentah: { "NS 1": number; "NS 2": number; Kecap: number; total: number };
  kristal: number;
  brondol: number;
}

export interface DashboardProduksiHariIni {
  tanggal: string;
  jumlahSesi: number;
  tungkuAktif: number;
  kgBahan: number;
  kgKristal: number;
  kgBrondol: number;
  totalProduksi: number;
  rendemen: number | null;
}

export interface DashboardKeuangan {
  pendapatanBulanIni: number;
  labaBulanIni: number;
  hppBulanIni: number;
  biayaOperasionalBulanIni: number;
  margin: number;
}

export interface DashboardTrenBulan {
  bulan: string;
  label: string;
  pendapatan: number;
  pembelian: number;
  gaji: number;
  biayaOperasional: number;
  totalBiaya: number;
  laba: number;
  margin: number;
}

export interface DashboardAktivitas {
  id: string;
  tanggal: string;
  modul: string;
  keterangan: string;
  nilai: string | null;
}

export interface DashboardRingkasan {
  tanggal: string;
  stok: DashboardStok;
  produksiHariIni: DashboardProduksiHariIni;
  rendemenBulanIni: number;
  keuangan?: DashboardKeuangan;
  tren?: DashboardTrenBulan[];
  aktivitasTerbaru: DashboardAktivitas[];
}

export async function getDashboard(): Promise<DashboardRingkasan> {
  const res = await apiClient.get<{ data: DashboardRingkasan }>("dashboard");
  return res.data;
}
