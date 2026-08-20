import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import * as api from "@/lib/api/pembelian";

const keys = {
  list: (params: api.PembelianListParams = {}) => ["pembelian", "list", params] as const,
  detail: (id: string) => ["pembelian", "detail", id] as const,
};

export function usePembelianList(params: api.PembelianListParams = {}) {
  return useQuery({
    queryKey: keys.list(params),
    queryFn: () => api.getPembelianList(params),
    // Data halaman lama tetap tampil saat pindah halaman, supaya tabel tidak
    // "berkedip" ke skeleton setiap ganti page.
    placeholderData: keepPreviousData,
  });
}

export function usePembelian(id: string | undefined) {
  return useQuery({
    queryKey: keys.detail(id ?? ""),
    queryFn: () => api.getPembelian(id as string),
    enabled: id !== undefined,
  });
}

export function useTambahPembelian() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: api.tambahPembelian,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["pembelian"] });
      // Stok bahan mentah & angka ringkasan dashboard ikut berubah otomatis di backend.
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
    },
  });
}

export function useBatalkanPembelian() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, alasan }: { id: string; alasan?: string | undefined }) =>
      api.batalkanPembelian(id, alasan),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["pembelian"] });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
    },
  });
}
