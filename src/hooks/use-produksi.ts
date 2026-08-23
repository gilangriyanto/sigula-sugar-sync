import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import * as api from "@/lib/api/produksi";

const keys = {
  list: (params: api.SesiListParams = {}) => ["produksi", "sesi", "list", params] as const,
  detail: (id: string) => ["produksi", "sesi", "detail", id] as const,
  tren: (hari: number, sampai?: string) =>
    ["produksi", "tren-rendemen", hari, sampai ?? null] as const,
};

export function useSesiList(params: api.SesiListParams = {}) {
  return useQuery({
    queryKey: keys.list(params),
    queryFn: () => api.getSesiList(params),
    placeholderData: keepPreviousData,
  });
}

export function useSesi(id: string | undefined) {
  return useQuery({
    queryKey: keys.detail(id ?? ""),
    queryFn: () => api.getSesi(id as string),
    enabled: id !== undefined,
  });
}

export function useTrenRendemen(hari = 14, sampai?: string) {
  return useQuery({
    queryKey: keys.tren(hari, sampai),
    queryFn: () => api.getTrenRendemen(hari, sampai),
  });
}

export function useMulaiSesi() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: api.mulaiSesi,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["produksi"] });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
    },
  });
}

export function useSelesaikanSesi() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: api.SelesaikanSesiPayload }) =>
      api.selesaikanSesi(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["produksi"] });
      // selesaikan() memotong stok bahan & menambah stok kristal/brondol.
      queryClient.invalidateQueries({ queryKey: ["stok"] });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
    },
  });
}

export function useBatalkanSesi() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, alasan }: { id: string; alasan?: string | undefined }) =>
      api.batalkanSesi(id, alasan),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["produksi"] });
      queryClient.invalidateQueries({ queryKey: ["stok"] });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
    },
  });
}
