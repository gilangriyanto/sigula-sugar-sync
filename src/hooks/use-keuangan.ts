import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import * as api from "@/lib/api/keuangan";

const keys = {
  labaRugi: (params: api.LabaRugiParams = {}) => ["keuangan", "laba-rugi", params] as const,
  tren: (bulan: number, sampai?: string) => ["keuangan", "tren", bulan, sampai ?? null] as const,
  biaya: (params: api.BiayaListParams = {}) => ["keuangan", "biaya", params] as const,
};

export function useLabaRugi(params: api.LabaRugiParams = {}) {
  return useQuery({
    queryKey: keys.labaRugi(params),
    queryFn: () => api.getLabaRugi(params),
  });
}

export function useTrenKeuangan(bulan = 6, sampai?: string) {
  return useQuery({
    queryKey: keys.tren(bulan, sampai),
    queryFn: () => api.getTrenKeuangan(bulan, sampai),
  });
}

export function useBiayaList(params: api.BiayaListParams = {}) {
  return useQuery({
    queryKey: keys.biaya(params),
    queryFn: () => api.getBiayaList(params),
    placeholderData: keepPreviousData,
  });
}

export function useTambahBiaya() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: api.tambahBiaya,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["keuangan"] });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
    },
  });
}

export function useUbahBiaya() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: Partial<api.BiayaCreatePayload> }) =>
      api.ubahBiaya(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["keuangan"] });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
    },
  });
}

export function useHapusBiaya() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: api.hapusBiaya,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["keuangan"] });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
    },
  });
}
