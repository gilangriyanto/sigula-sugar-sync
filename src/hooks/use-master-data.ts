import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import * as api from "@/lib/api/master";

/**
 * Hooks React Query untuk modul Master Data (Harga, Tarif, Karyawan, Eksportir).
 * Dipakai langsung di halaman Master, dan dipakai ulang sebagai sumber
 * dropdown/referensi di modul Pembelian, Produksi, Penggajian, dan Penjualan.
 */

const keys = {
  harga: (grade?: api.GradeKode) => ["master", "harga", grade ?? null] as const,
  tarif: (jenis?: api.JenisTarifKode) => ["master", "tarif", jenis ?? null] as const,
  karyawan: (params: api.KaryawanListParams = {}) => ["master", "karyawan", params] as const,
  eksportir: (params: api.EksportirListParams = {}) => ["master", "eksportir", params] as const,
};

// ---- Harga --------------------------------------------------------------

export function useHargaBeli(grade?: api.GradeKode) {
  return useQuery({
    queryKey: keys.harga(grade),
    queryFn: () => api.getHarga(grade),
  });
}

export function useUbahHarga() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: api.ubahHarga,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["master", "harga"] });
    },
  });
}

// ---- Tarif ----------------------------------------------------------------

export function useTarif(jenis?: api.JenisTarifKode) {
  return useQuery({
    queryKey: keys.tarif(jenis),
    queryFn: () => api.getTarif(jenis),
  });
}

export function useUbahTarif() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: api.ubahTarif,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["master", "tarif"] });
    },
  });
}

// ---- Karyawan ---------------------------------------------------------------

export function useKaryawanList(params: api.KaryawanListParams = {}) {
  return useQuery({
    queryKey: keys.karyawan(params),
    queryFn: () => api.getKaryawanList(params),
  });
}

export function useTambahKaryawan() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: api.tambahKaryawan,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["master", "karyawan"] });
    },
  });
}

export function useUbahKaryawan() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: api.KaryawanPayload }) =>
      api.ubahKaryawan(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["master", "karyawan"] });
    },
  });
}

export function useHapusKaryawan() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: api.hapusKaryawan,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["master", "karyawan"] });
    },
  });
}

// ---- Eksportir ----------------------------------------------------------------

export function useEksportirList(params: api.EksportirListParams = {}) {
  return useQuery({
    queryKey: keys.eksportir(params),
    queryFn: () => api.getEksportirList(params),
  });
}

export function useTambahEksportir() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: api.tambahEksportir,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["master", "eksportir"] });
    },
  });
}

export function useUbahEksportir() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: api.EksportirPayload }) =>
      api.ubahEksportir(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["master", "eksportir"] });
    },
  });
}

export function useHapusEksportir() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: api.hapusEksportir,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["master", "eksportir"] });
    },
  });
}
