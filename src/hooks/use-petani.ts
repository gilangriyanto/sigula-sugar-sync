import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import * as api from "@/lib/api/petani";

/**
 * Hooks React Query untuk modul Data Petani. `usePetaniList` dipakai ulang
 * sebagai sumber dropdown pencarian petani di modul Pembelian.
 */

const keys = {
  list: (params: api.PetaniListParams = {}) => ["petani", "list", params] as const,
  detail: (id: string) => ["petani", "detail", id] as const,
};

export function usePetaniList(params: api.PetaniListParams = {}) {
  return useQuery({
    queryKey: keys.list(params),
    queryFn: () => api.getPetaniList(params),
  });
}

export function usePetani(id: string | undefined) {
  return useQuery({
    queryKey: keys.detail(id ?? ""),
    queryFn: () => api.getPetani(id as string),
    enabled: id !== undefined,
  });
}

export function useTambahPetani() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: api.tambahPetani,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["petani"] });
    },
  });
}

export function useUbahPetani() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: api.PetaniPayload }) =>
      api.ubahPetani(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["petani"] });
    },
  });
}

export function useHapusPetani() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: api.hapusPetani,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["petani"] });
    },
  });
}
