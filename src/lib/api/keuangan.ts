import { apiClient } from "@/lib/api-client";
import type { KategoriBiaya } from "@/lib/sigula-types";

/**
 * Bentuk data persis seperti folder "10. Keuangan & Laporan" di
 * SIGULA.postman_collection.json, cross-check ke LaporanController /
 * LaporanService / BiayaOperasionalController / BiayaOperasionalRequest.
 *
 * Khusus Owner — Staff Gudang & Staff Produksi menerima 403 di seluruh
 * endpoint ini (sidebar sudah menyembunyikannya lewat `menu` dari /auth/me).
 */

export interface LabaRugiGaji {
  upahKristal: number;
  upahBrondol: number;
  uangMakan: number;
  total: number;
}

export interface LabaRugiHpp {
  bahan: number;
  gaji: LabaRugiGaji;
  total: number;
}

export interface LabaRugi {
  periode: { dari: string; sampai: string };
  pendapatan: number;
  hpp: LabaRugiHpp;
  biayaOperasional: number;
  labaBersih: number;
  margin: number;
}

export type PeriodeLabaRugi = "bulan_ini" | "bulan_lalu" | "custom";

export interface LabaRugiParams {
  periode?: PeriodeLabaRugi | undefined;
  /** Wajib bila periode="custom". */
  dari?: string | undefined;
  sampai?: string | undefined;
}

export async function getLabaRugi(params: LabaRugiParams = {}): Promise<LabaRugi> {
  const res = await apiClient.get<{ data: LabaRugi }>("keuangan/laba-rugi", {
    periode: params.periode,
    dari: params.dari,
    sampai: params.sampai,
  });
  return res.data;
}

export interface TrenKeuanganBulan {
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

export async function getTrenKeuangan(bulan = 6, sampai?: string): Promise<TrenKeuanganBulan[]> {
  const res = await apiClient.get<{ data: TrenKeuanganBulan[] }>("keuangan/tren", {
    bulan,
    sampai,
  });
  return res.data;
}

export interface Biaya {
  id: string;
  tanggal: string;
  tanggalLabel: string;
  keterangan: string;
  kategori: KategoriBiaya;
  kategoriKode: "listrik" | "transport" | "sewa" | "lainnya";
  jumlah: number;
}

/** Bentuk default Laravel `paginate()->toResourceCollection()` — snake_case. */
export interface BiayaMeta {
  current_page: number;
  from: number | null;
  last_page: number;
  per_page: number;
  to: number | null;
  total: number;
}

export interface BiayaListParams {
  dari?: string | undefined;
  sampai?: string | undefined;
  kategori?: KategoriBiaya | undefined;
  page?: number | undefined;
  perPage?: number | undefined;
}

export interface BiayaListResult {
  data: Biaya[];
  meta: BiayaMeta;
  ringkasan: { total: number };
}

export async function getBiayaList(params: BiayaListParams = {}): Promise<BiayaListResult> {
  return apiClient.get<BiayaListResult>("keuangan/biaya", {
    dari: params.dari,
    sampai: params.sampai,
    kategori: params.kategori,
    page: params.page,
    perPage: params.perPage,
  });
}

export interface BiayaCreatePayload {
  tanggal: string;
  keterangan: string;
  kategori: KategoriBiaya;
  jumlah: number;
}

export async function tambahBiaya(payload: BiayaCreatePayload): Promise<Biaya> {
  const res = await apiClient.post<{ data: Biaya; message: string }>("keuangan/biaya", payload);
  return res.data;
}

/** Patch parsial — kirim hanya field yang berubah. */
export async function ubahBiaya(id: string, payload: Partial<BiayaCreatePayload>): Promise<Biaya> {
  const res = await apiClient.put<{ data: Biaya; message: string }>(
    `keuangan/biaya/${id}`,
    payload,
  );
  return res.data;
}

export async function hapusBiaya(id: string): Promise<void> {
  await apiClient.delete(`keuangan/biaya/${id}`);
}
