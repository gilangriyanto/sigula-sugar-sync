import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import * as api from "@/lib/api/penjualan";

const keys = {
  list: (params: api.PenjualanListParams = {}) => ["penjualan", "list", params] as const,
  detail: (id: string) => ["penjualan", "detail", id] as const,
};

export function usePenjualanList(params: api.PenjualanListParams = {}) {
  return useQuery({
    queryKey: keys.list(params),
    queryFn: () => api.getPenjualanList(params),
    placeholderData: keepPreviousData,
  });
}

export function usePenjualan(id: string | undefined) {
  return useQuery({
    queryKey: keys.detail(id ?? ""),
    queryFn: () => api.getPenjualan(id as string),
    enabled: id !== undefined,
  });
}

export function useTambahPenjualan() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: api.tambahPenjualan,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["penjualan"] });
      // Stok kristal/brondol berkurang otomatis.
      queryClient.invalidateQueries({ queryKey: ["stok"] });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
    },
  });
}

export function useUbahStatusPenjualan() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, status }: { id: string; status: api.StatusPembayaranKode }) =>
      api.ubahStatusPenjualan(id, status),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["penjualan"] });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
    },
  });
}

export function useBatalkanPenjualan() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, alasan }: { id: string; alasan?: string | undefined }) =>
      api.batalkanPenjualan(id, alasan),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["penjualan"] });
      queryClient.invalidateQueries({ queryKey: ["stok"] });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
    },
  });
}
