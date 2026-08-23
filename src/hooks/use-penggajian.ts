import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import * as api from "@/lib/api/penggajian";

const keys = {
  rekap: (params: api.RekapGajiParams = {}) => ["penggajian", "rekap", params] as const,
  slip: (karyawanId: string, tanggal?: string) =>
    ["penggajian", "slip", karyawanId, tanggal ?? null] as const,
};

export function useRekapGaji(params: api.RekapGajiParams = {}) {
  return useQuery({
    queryKey: keys.rekap(params),
    queryFn: () => api.getRekapGaji(params),
  });
}

export function useSlipGaji(karyawanId: string | undefined, tanggal?: string) {
  return useQuery({
    queryKey: keys.slip(karyawanId ?? "", tanggal),
    queryFn: () => api.getSlipGaji(karyawanId as string, tanggal),
    enabled: karyawanId !== undefined,
  });
}

export function useBayarGaji() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ karyawanId, tanggal }: { karyawanId: string; tanggal?: string | undefined }) =>
      api.bayarGaji(karyawanId, tanggal),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["penggajian"] });
      // Beban gaji adalah komponen HPP di laporan laba rugi / dashboard.
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
    },
  });
}

export function useBayarSemuaGaji() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (tanggal: string | undefined) => api.bayarSemuaGaji(tanggal),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["penggajian"] });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
    },
  });
}
