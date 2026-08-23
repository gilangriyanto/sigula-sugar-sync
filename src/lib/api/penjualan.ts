import { apiClient } from "@/lib/api-client";

/**
 * Bentuk data persis seperti folder "9. Penjualan ke Eksportir" di
 * SIGULA.postman_collection.json, cross-check ke PenjualanController /
 * PenjualanRequest / PenjualanResource / PenjualanService.
 *
 * Penting: satu invoice = DUA OBJEK TERPISAH `kristal` & `brondol` (masing-
 * masing `{ kg, harga }`, opsional, kirim null/hilangkan bila tidak dijual)
 * — BUKAN array of line items. Backend menghitung subtotal & total sendiri
 * dari kg+harga final; kalkulasi dua arah (edit subtotal → harga menyesuaikan)
 * murni logic frontend.
 */

export interface BarisJual {
  kg: number;
  harga: number;
  subtotal: number;
}

export type StatusPembayaran = "Lunas" | "Belum Lunas";
export type StatusPembayaranKode = "lunas" | "belum_lunas";

export interface Penjualan {
  id: string;
  noInvoice: string;
  tanggal: string;
  tanggalLabel: string;
  eksportirId: string;
  namaEksportir: string | null;
  kristal: BarisJual | null;
  brondol: BarisJual | null;
  total: number;
  statusPembayaran: StatusPembayaran;
  statusPembayaranKode: StatusPembayaranKode;
  dibayarPada: string | null;
  catatan: string | null;
}

export interface PenjualanRingkasan {
  rupiah: number;
  kgKristal: number;
  kgBrondol: number;
  rupiahKristal: number;
  rupiahBrondol: number;
  jumlahTransaksi: number;
}

/** Bentuk default Laravel `paginate()->toResourceCollection()` — snake_case. */
export interface PenjualanMeta {
  current_page: number;
  from: number | null;
  last_page: number;
  per_page: number;
  to: number | null;
  total: number;
}

export interface PenjualanListParams {
  dari?: string | undefined;
  sampai?: string | undefined;
  eksportirId?: string | undefined;
  q?: string | undefined;
  page?: number | undefined;
  perPage?: number | undefined;
}

export interface PenjualanListResult {
  data: Penjualan[];
  meta: PenjualanMeta;
  ringkasan: PenjualanRingkasan;
}

export interface BarisJualInput {
  kg: number;
  harga: number;
}

export interface PenjualanPayload {
  tanggal: string;
  eksportirId: string;
  /** Minimal salah satu dari kristal/brondol harus diisi. */
  kristal?: BarisJualInput | null | undefined;
  brondol?: BarisJualInput | null | undefined;
  statusPembayaran?: StatusPembayaranKode | undefined;
  catatan?: string | undefined;
}

export interface InvoiceBaris {
  jenis: string;
  kilogram: number;
  hargaPerKg: number;
  subtotal: number;
}

export interface PenjualanInvoice {
  nomor: string;
  tanggal: string;
  eksportir: string | null;
  /** Kristal selalu lebih dulu, lalu brondol. */
  baris: InvoiceBaris[];
  total: number;
  statusPembayaran: string;
}

export async function getPenjualanList(
  params: PenjualanListParams = {},
): Promise<PenjualanListResult> {
  return apiClient.get<PenjualanListResult>("penjualan", {
    dari: params.dari,
    sampai: params.sampai,
    eksportirId: params.eksportirId,
    q: params.q,
    page: params.page,
    perPage: params.perPage,
  });
}

export async function getPenjualan(
  id: string,
): Promise<{ penjualan: Penjualan; invoice: PenjualanInvoice }> {
  const res = await apiClient.get<{ data: Penjualan; invoice: PenjualanInvoice }>(
    `penjualan/${id}`,
  );
  return { penjualan: res.data, invoice: res.invoice };
}

export async function tambahPenjualan(
  payload: PenjualanPayload,
): Promise<{ penjualan: Penjualan; invoice: PenjualanInvoice; message: string }> {
  const res = await apiClient.post<{
    data: Penjualan;
    invoice: PenjualanInvoice;
    message: string;
  }>("penjualan", payload);
  return { penjualan: res.data, invoice: res.invoice, message: res.message };
}

export async function ubahStatusPenjualan(
  id: string,
  statusPembayaran: StatusPembayaranKode,
): Promise<{ penjualan: Penjualan; message: string }> {
  const res = await apiClient.patch<{ data: Penjualan; message: string }>(
    `penjualan/${id}/status`,
    { statusPembayaran },
  );
  return { penjualan: res.data, message: res.message };
}

export async function batalkanPenjualan(id: string, alasan?: string): Promise<{ message: string }> {
  return apiClient.delete<{ message: string }>(`penjualan/${id}`, alasan ? { alasan } : undefined);
}
