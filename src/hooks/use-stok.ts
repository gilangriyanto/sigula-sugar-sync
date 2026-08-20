import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import * as api from "@/lib/api/stok";

const keys = {
  posisi: () => ["stok", "posisi"] as const,
  kartu: (params: api.KartuStokListParams = {}) => ["stok", "kartu", params] as const,
};

export function useStokPosisi() {
  return useQuery({
    queryKey: keys.posisi(),
    queryFn: api.getStokPosisi,
  });
}

export function useKartuStok(params: api.KartuStokListParams = {}) {
  return useQuery({
    queryKey: keys.kartu(params),
    queryFn: () => api.getKartuStok(params),
    // Data halaman lama tetap tampil saat pindah halaman, supaya tabel tidak
    // "berkedip" ke skeleton setiap ganti page.
    placeholderData: keepPreviousData,
  });
}

export function useStokOpname() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: api.stokOpname,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["stok"] });
      // Kartu "Total Stok Bahan Mentah" dsb. di dashboard ikut berubah.
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
    },
  });
}
