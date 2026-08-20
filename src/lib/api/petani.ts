import { apiClient } from "@/lib/api-client";

/**
 * Bentuk data persis seperti folder "3. Data Petani" di
 * SIGULA.postman_collection.json, cross-check ke PetaniController /
 * PetaniRequest / PetaniResource / MasterDataService.
 */

export interface Petani {
  id: string;
  nama: string;
  status: "Member" | "Non-Member";
  statusKode: "member" | "non_member";
  /** Kosong untuk Non-Member. Digenerate backend, jangan dikirim dari form. */
  nomorMember: string;
  /** "Petani {nomorMember}", kosong untuk Non-Member. */
  labelMember: string;
  kontak: string;
  alamat: string;
  totalTransaksi: number;
  totalNilai: number;
}

export interface PetaniListParams {
  q?: string | undefined;
}

export interface PetaniPayload {
  nama: string;
  status: "Member" | "Non-Member";
  kontak?: string | undefined;
  alamat?: string | undefined;
}

export async function getPetaniList(params: PetaniListParams = {}): Promise<Petani[]> {
  const res = await apiClient.get<{ data: Petani[] }>("petani", { q: params.q });
  return res.data;
}

export async function getPetani(id: string): Promise<Petani> {
  const res = await apiClient.get<{ data: Petani }>(`petani/${id}`);
  return res.data;
}

export async function tambahPetani(payload: PetaniPayload): Promise<Petani> {
  const res = await apiClient.post<{ data: Petani }>("petani", payload);
  return res.data;
}

export async function ubahPetani(id: string, payload: PetaniPayload): Promise<Petani> {
  const res = await apiClient.put<{ data: Petani }>(`petani/${id}`, payload);
  return res.data;
}

export async function hapusPetani(id: string): Promise<void> {
  await apiClient.delete(`petani/${id}`);
}
