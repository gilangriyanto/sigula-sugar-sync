import { apiClient } from "@/lib/api-client";
import type { StokKategori } from "@/lib/sigula-types";

/**
 * Bentuk data persis seperti folder "6. Manajemen Stok" di
 * SIGULA.postman_collection.json, cross-check ke StokController /
 * StokOpnameRequest / KartuStokResource / StokService.
 */

export type StokKategoriKode = "ns1" | "ns2" | "kecap" | "kristal" | "brondol";

export interface StokKategoriInfo {
  kode: StokKategoriKode;
  nama: StokKategori;
  /** Label panjang, mis. "Bahan Mentah NS 1" / "Produk Kristal". */
  label: string;
  saldo: number;
  status: "aman" | "menipis";
}

export interface StokPosisi {
  /** Sama persis dengan state `stok` lama di frontend: { "NS 1": 1200, ... }. */
  saldo: Record<StokKategori, number>;
  bahanMentah: StokKategoriInfo[];
  totalBahanMentah: number;
  produkJadi: StokKategoriInfo[];
  ambangMenipis: number;
}

export async function getStokPosisi(): Promise<StokPosisi> {
  const res = await apiClient.get<{ data: StokPosisi }>("stok");
  return res.data;
}

export type JenisMutasi = "Masuk" | "Keluar";
export type JenisMutasiKode = "masuk" | "keluar";

export interface KartuStokRow {
  id: string;
  tanggal: string;
  tanggalLabel: string;
  jenis: JenisMutasi;
  jenisKode: JenisMutasiKode;
  kategori: StokKategori;
  kategoriKode: StokKategoriKode;
  /** Label panjang, mis. "Bahan Mentah NS 1". */
  kategoriLabel: string;
  jumlah: number;
  saldoSetelah: number;
  keterangan: string;
  /** Menunjuk transaksi asal: "pembelian" | "sesi_tungku" | "penjualan" | null (opname manual). */
  referensiTipe: string | null;
  referensiId: string | null;
}

/** Bentuk default Laravel `paginate()->toResourceCollection()` — snake_case, tanpa `ringkasan`. */
export interface KartuStokMeta {
  current_page: number;
  from: number | null;
  last_page: number;
  per_page: number;
  to: number | null;
  total: number;
}

export interface KartuStokListParams {
  kategori?: StokKategori | undefined;
  jenis?: JenisMutasi | undefined;
  dari?: string | undefined;
  sampai?: string | undefined;
  q?: string | undefined;
  page?: number | undefined;
  perPage?: number | undefined;
}

export interface KartuStokListResult {
  data: KartuStokRow[];
  meta: KartuStokMeta;
}

export async function getKartuStok(params: KartuStokListParams = {}): Promise<KartuStokListResult> {
  return apiClient.get<KartuStokListResult>("stok/kartu", {
    kategori: params.kategori,
    jenis: params.jenis,
    dari: params.dari,
    sampai: params.sampai,
    q: params.q,
    page: params.page,
    perPage: params.perPage,
  });
}

export interface StokOpnamePayload {
  kategori: StokKategori;
  /** Jumlah fisik hasil hitung gudang — BUKAN selisih. Backend menghitung selisihnya sendiri. */
  stokFisik: number;
  alasan: string;
  tanggal?: string | undefined;
}

export async function stokOpname(payload: StokOpnamePayload): Promise<KartuStokRow> {
  const res = await apiClient.post<{ message: string; data: KartuStokRow }>("stok/opname", payload);
  return res.data;
}
