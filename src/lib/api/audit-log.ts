import { apiClient } from "@/lib/api-client";

/**
 * Bentuk data persis seperti request "Audit log" di folder "10. Keuangan &
 * Laporan" di SIGULA.postman_collection.json, cross-check ke
 * AuditLogController.
 *
 * Penting: controller ini membangun `meta` secara manual (camelCase),
 * BUKAN lewat `paginate()->toResourceCollection()` default Laravel seperti
 * kebanyakan list endpoint lain (yang snake_case).
 */

export interface AuditLogUser {
  id: string;
  nama: string;
  role: string;
}

export interface AuditLogEntry {
  id: string;
  aksi: string;
  deskripsi: string;
  user: AuditLogUser | null;
  data: Record<string, unknown> | null;
  ip: string | null;
  waktu: string | null;
}

export interface AuditLogMeta {
  currentPage: number;
  lastPage: number;
  perPage: number;
  total: number;
}

export interface AuditLogListParams {
  /** Prefix, mis. "harga." / "produksi." / "gaji." / "stok.". */
  aksi?: string | undefined;
  userId?: string | undefined;
  dari?: string | undefined;
  sampai?: string | undefined;
  page?: number | undefined;
  perPage?: number | undefined;
}

export interface AuditLogListResult {
  data: AuditLogEntry[];
  meta: AuditLogMeta;
}

export async function getAuditLog(params: AuditLogListParams = {}): Promise<AuditLogListResult> {
  return apiClient.get<AuditLogListResult>("audit-log", {
    aksi: params.aksi,
    userId: params.userId,
    dari: params.dari,
    sampai: params.sampai,
    page: params.page,
    perPage: params.perPage,
  });
}
