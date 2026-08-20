import { apiClient } from "@/lib/api-client";
import type { Grade } from "@/lib/sigula-types";

/**
 * Bentuk data persis seperti folder "5. Pembelian Bahan dari Petani" di
 * SIGULA.postman_collection.json, cross-check ke PembelianController /
 * PembelianRequest / PembelianResource / PembelianService.
 *
 * Penting: index() memakai `PembelianResource::collection($paginator)` tanpa
 * override, jadi `meta`/`links` memakai bentuk default Laravel (snake_case),
 * BUKAN gaya camelCase yang dipakai modul lain (mis. audit-log) yang
 * membangun meta-nya sendiri secara manual.
 */

export interface Pembelian {
  id: string;
  nomorKwitansi: string;
  tanggal: string;
  tanggalLabel: string;
  petaniId: string;
  namaPetani: string | null;
  grade: Grade;
  gradeKode: "ns1" | "ns2" | "kecap";
  kg: number;
  harga: number;
  total: number;
  statusPembayaran: "Lunas" | "Belum Lunas";
  statusPembayaranKode: "lunas" | "belum_lunas";
  catatan: string | null;
  dibuatPada: string | null;
}

export interface PembelianRingkasan {
  hariIni: number;
  kgHariIni: number;
  bulanIni: number;
  kgBulanIni: number;
}

/** Bentuk default Laravel `paginate()->toResourceCollection()` — snake_case. */
export interface PembelianMeta {
  current_page: number;
  from: number | null;
  last_page: number;
  per_page: number;
  to: number | null;
  total: number;
}

export interface PembelianListParams {
  dari?: string | undefined;
  sampai?: string | undefined;
  petaniId?: string | undefined;
  grade?: Grade | undefined;
  q?: string | undefined;
  page?: number | undefined;
  perPage?: number | undefined;
}

export interface PembelianListResult {
  data: Pembelian[];
  meta: PembelianMeta;
  ringkasan: PembelianRingkasan;
}

export interface PembelianPayload {
  tanggal: string;
  petaniId: string;
  grade: Grade;
  kg: number;
  /** Kosongkan (omit/null) → backend pakai harga master yang berlaku pada `tanggal`. */
  harga?: number | null | undefined;
  catatan?: string;
}

export interface PembelianKwitansi {
  nomor: string;
  tanggal: string;
  namaPetani: string | null;
  nomorMember: string;
  grade: string;
  kilogram: number;
  hargaPerKg: number;
  total: number;
  statusPembayaran: string;
}

export async function getPembelianList(
  params: PembelianListParams = {},
): Promise<PembelianListResult> {
  return apiClient.get<PembelianListResult>("pembelian", {
    dari: params.dari,
    sampai: params.sampai,
    petaniId: params.petaniId,
    grade: params.grade,
    q: params.q,
    page: params.page,
    perPage: params.perPage,
  });
}

export async function getPembelian(
  id: string,
): Promise<{ pembelian: Pembelian; kwitansi: PembelianKwitansi }> {
  const res = await apiClient.get<{ data: Pembelian; kwitansi: PembelianKwitansi }>(
    `pembelian/${id}`,
  );
  return { pembelian: res.data, kwitansi: res.kwitansi };
}

export async function tambahPembelian(
  payload: PembelianPayload,
): Promise<{ pembelian: Pembelian; kwitansi: PembelianKwitansi; message: string }> {
  const res = await apiClient.post<{
    data: Pembelian;
    kwitansi: PembelianKwitansi;
    message: string;
  }>("pembelian", payload);
  return { pembelian: res.data, kwitansi: res.kwitansi, message: res.message };
}

export async function batalkanPembelian(id: string, alasan?: string): Promise<{ message: string }> {
  return apiClient.delete<{ message: string }>(`pembelian/${id}`, alasan ? { alasan } : undefined);
}
