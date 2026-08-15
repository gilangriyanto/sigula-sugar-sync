# Menyambungkan Frontend React ke API SIGULA

Prototype frontend (`../src`) saat ini memakai `SigulaProvider` dengan state di memori
(`src/store/sigula-store.tsx` + `src/lib/sigula-seed.ts`). Backend ini dirancang supaya
penggantiannya semulus mungkin: **bentuk JSON API sengaja dibuat sama dengan tipe data
yang sudah dipakai frontend** (`src/lib/sigula-types.ts`), jadi tidak perlu lapisan
konversi.

Dokumen ini berisi urutan migrasi yang disarankan beserta kode yang bisa langsung dipakai.

---

## 1. Yang sudah cocok tanpa perubahan

| Tipe frontend | Endpoint | Catatan |
|---|---|---|
| `Petani` | `GET /petani` | `id`, `nama`, `status`, `nomorMember`, `kontak`, `alamat` identik |
| `Karyawan` | `GET /master/karyawan` | identik |
| `Eksportir` | `GET /master/eksportir` | identik |
| `Pembelian` | `GET /pembelian` | `tanggal`, `petaniId`, `grade`, `kg`, `harga`, `total` identik |
| `StokMove` | `GET /stok/kartu` | `tanggal`, `jenis`, `kategori`, `jumlah`, `keterangan` identik |
| `SesiTungku` | `GET /produksi/sesi` | `kodeTungku`, `kgBahan`, `karyawanIds`, `kgKristal`, `kgBrondol`, `status` identik |
| `Penjualan` | `GET /penjualan` | `noInvoice`, `eksportirId`, `kristal`/`brondol` (`{kg,harga}`), `total` identik |
| `Biaya` | `GET /keuangan/biaya` | identik |
| `BarisGaji` | `GET /penggajian` → `data.baris` | identik, sudah termasuk `dibayar` |
| `Tarif` | `GET /master/tarif` → `data.tarif` | `{ kristal, brondol, uangMakan }` |
| `hargaBeli` | `GET /master/harga` → `data.hargaBeli` | `{ "NS 1": 14500, ... }` |
| `stok` | `GET /stok` → `data.saldo` | `{ "NS 1": 1886, ..., "Kristal": 4075.2 }` |
| `RiwayatHarga` | `GET /master/harga` → `data.riwayat` | `hargaLama`/`hargaBaru` sudah diturunkan server |

Perbedaan yang perlu diperhatikan:

1. **Perhitungan pindah ke server.** `payrollMinggu()`, `gajiBulan()`, dan agregasi laba
   rugi tidak perlu lagi dihitung di frontend — panggil `/penggajian` dan
   `/keuangan/laba-rugi`.
2. **Endpoint transaksi berpaginasi.** `/pembelian`, `/penjualan`, `/produksi/sesi`,
   `/stok/kartu`, `/keuangan/biaya` mengembalikan `data` + `meta`. Filter yang tadinya
   client-side (tanggal, grade, status, pencarian) sudah tersedia sebagai query param.
3. **Field tambahan** seperti `nomorKwitansi`, `rendemen`, `tanggalLabel`, `namaPetani`,
   `gradeKode` boleh diabaikan atau dipakai untuk menyederhanakan tampilan.

---

## 2. Konfigurasi

`.env` frontend:

```dotenv
VITE_API_URL=http://localhost:8000/api/v1
```

`.env` backend — daftarkan origin frontend agar lolos CORS:

```dotenv
FRONTEND_URL=http://localhost:3000,http://localhost:5173
```

---

## 3. API client

Simpan sebagai `src/lib/sigula-api.ts`:

```ts
import type {
  BarisGaji, Biaya, Eksportir, Grade, Karyawan, Pembelian,
  Penjualan, Petani, SesiTungku, StokKategori, StokMove, Tarif,
} from "./sigula-types";

const BASE = import.meta.env.VITE_API_URL ?? "http://localhost:8000/api/v1";
const TOKEN_KEY = "sigula.token";

export const token = {
  get: () => localStorage.getItem(TOKEN_KEY),
  set: (value: string) => localStorage.setItem(TOKEN_KEY, value),
  clear: () => localStorage.removeItem(TOKEN_KEY),
};

/** Error 422 dari server: pesan utama + error per field. */
export class ApiError extends Error {
  constructor(
    message: string,
    readonly status: number,
    readonly errors: Record<string, string[]> = {},
  ) {
    super(message);
  }

  /** Pesan untuk satu field form, mis. field("kristal.kg") */
  field(name: string): string | undefined {
    return this.errors[name]?.[0];
  }
}

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const auth = token.get();

  const response = await fetch(`${BASE}${path}`, {
    ...init,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(auth ? { Authorization: `Bearer ${auth}` } : {}),
      ...init.headers,
    },
  });

  if (response.status === 401) {
    token.clear();
    throw new ApiError("Sesi berakhir, silakan masuk kembali.", 401);
  }

  const body = response.status === 204 ? null : await response.json();

  if (!response.ok) {
    throw new ApiError(body?.message ?? "Terjadi kesalahan.", response.status, body?.errors ?? {});
  }

  return body as T;
}

const qs = (params: Record<string, unknown> = {}) => {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== "") search.set(key, String(value));
  });
  const query = search.toString();
  return query ? `?${query}` : "";
};

const get = <T>(path: string, params?: Record<string, unknown>) =>
  request<T>(`${path}${qs(params)}`);
const post = <T>(path: string, body?: unknown) =>
  request<T>(path, { method: "POST", body: JSON.stringify(body ?? {}) });
const put = <T>(path: string, body: unknown) =>
  request<T>(path, { method: "PUT", body: JSON.stringify(body) });
const patch = <T>(path: string, body: unknown) =>
  request<T>(path, { method: "PATCH", body: JSON.stringify(body) });
const del = <T>(path: string) => request<T>(path, { method: "DELETE" });

interface Envelope<T> { data: T; message?: string }
interface Paginated<T> extends Envelope<T[]> {
  meta: { current_page: number; last_page: number; per_page: number; total: number };
  ringkasan?: Record<string, number>;
}

export interface UserSigula {
  id: string; nama: string; email: string; role: string; roleLabel: string;
  menu: string[]; abilities: string[];
}

export const api = {
  auth: {
    login: (email: string, password: string) =>
      post<Envelope<{ token: string; user: UserSigula }>>("/auth/login", { email, password }),
    me: () => get<Envelope<UserSigula>>("/auth/me"),
    logout: () => post<{ message: string }>("/auth/logout"),
  },

  dashboard: () => get<Envelope<any>>("/dashboard"),

  petani: {
    daftar: (q?: string) => get<Envelope<Petani[]>>("/petani", { q }),
    simpan: (data: Omit<Petani, "id">) => post<Envelope<Petani>>("/petani", data),
    ubah: (id: string, data: Omit<Petani, "id">) => put<Envelope<Petani>>(`/petani/${id}`, data),
    hapus: (id: string) => del<{ message: string }>(`/petani/${id}`),
  },

  master: {
    harga: () => get<Envelope<{ hargaBeli: Record<Grade, number>; riwayat: any[] }>>("/master/harga"),
    ubahHarga: (grade: Grade, harga: number, berlakuDari?: string) =>
      post<Envelope<any>>("/master/harga", { grade, harga, berlakuDari }),
    tarif: () => get<Envelope<{ tarif: Tarif; riwayat: any[] }>>("/master/tarif"),
    ubahTarif: (jenis: "kristal" | "brondol" | "uangMakan", nilai: number) =>
      post<Envelope<any>>("/master/tarif", { jenis, nilai }),
    karyawan: () => get<Envelope<Karyawan[]>>("/master/karyawan"),
    simpanKaryawan: (data: Omit<Karyawan, "id">) => post<Envelope<Karyawan>>("/master/karyawan", data),
    hapusKaryawan: (id: string) => del<{ message: string }>(`/master/karyawan/${id}`),
    eksportir: () => get<Envelope<Eksportir[]>>("/master/eksportir"),
    simpanEksportir: (data: Omit<Eksportir, "id">) => post<Envelope<Eksportir>>("/master/eksportir", data),
    hapusEksportir: (id: string) => del<{ message: string }>(`/master/eksportir/${id}`),
  },

  pembelian: {
    daftar: (params?: { dari?: string; sampai?: string; petaniId?: string; grade?: Grade; q?: string; page?: number; perPage?: number }) =>
      get<Paginated<Pembelian>>("/pembelian", params),
    simpan: (data: { tanggal: string; petaniId: string; grade: Grade; kg: number; harga?: number | null }) =>
      post<Envelope<Pembelian> & { kwitansi: any }>("/pembelian", data),
    batalkan: (id: string) => del<{ message: string }>(`/pembelian/${id}`),
  },

  stok: {
    posisi: () => get<Envelope<{ saldo: Record<StokKategori, number>; bahanMentah: any[]; produkJadi: any[] }>>("/stok"),
    kartu: (params?: { kategori?: string; jenis?: "masuk" | "keluar"; dari?: string; sampai?: string; q?: string; page?: number; perPage?: number }) =>
      get<Paginated<StokMove>>("/stok/kartu", params),
    opname: (data: { kategori: StokKategori; stokFisik: number; alasan: string; tanggal?: string }) =>
      post<Envelope<StokMove>>("/stok/opname", data),
  },

  produksi: {
    sesi: (params?: { tanggal?: string; status?: string; grade?: Grade; q?: string; page?: number; perPage?: number }) =>
      get<Paginated<SesiTungku>>("/produksi/sesi", params),
    mulai: (data: { tanggal: string; kodeTungku?: string; grade: Grade; kgBahan: number; karyawan1Id: string; karyawan2Id: string }) =>
      post<Envelope<SesiTungku>>("/produksi/sesi", data),
    selesaikan: (id: string, kgKristal: number, kgBrondol: number) =>
      post<Envelope<SesiTungku>>(`/produksi/sesi/${id}/selesai`, { kgKristal, kgBrondol }),
    batalkan: (id: string) => del<{ message: string }>(`/produksi/sesi/${id}`),
    trenRendemen: (hari = 14) => get<Envelope<any[]>>("/produksi/tren-rendemen", { hari }),
  },

  penggajian: {
    rekap: (tanggal?: string) =>
      get<Envelope<{ periode: { senin: string; jumat: string; label: string }; tarif: Tarif; baris: BarisGaji[]; ringkasan: any }>>(
        "/penggajian", { tanggal },
      ),
    slip: (karyawanId: string, tanggal?: string) => get<Envelope<any>>(`/penggajian/slip/${karyawanId}`, { tanggal }),
    bayar: (karyawanId: string, tanggal?: string) => post<Envelope<any>>(`/penggajian/${karyawanId}/bayar`, { tanggal }),
    bayarSemua: (tanggal?: string) => post<Envelope<any>>("/penggajian/bayar-semua", { tanggal }),
  },

  penjualan: {
    daftar: (params?: { dari?: string; sampai?: string; q?: string; page?: number; perPage?: number }) =>
      get<Paginated<Penjualan>>("/penjualan", params),
    simpan: (data: {
      tanggal: string; eksportirId: string;
      kristal?: { kg: number; harga: number } | null;
      brondol?: { kg: number; harga: number } | null;
    }) => post<Envelope<Penjualan> & { invoice: any }>("/penjualan", data),
    ubahStatus: (id: string, statusPembayaran: "lunas" | "belum_lunas") =>
      patch<Envelope<Penjualan>>(`/penjualan/${id}/status`, { statusPembayaran }),
    batalkan: (id: string) => del<{ message: string }>(`/penjualan/${id}`),
  },

  keuangan: {
    labaRugi: (params?: { periode?: "bulan_ini" | "bulan_lalu" | "custom"; dari?: string; sampai?: string }) =>
      get<Envelope<any>>("/keuangan/laba-rugi", params),
    tren: (bulan = 6) => get<Envelope<any[]>>("/keuangan/tren", { bulan }),
    biaya: (params?: { dari?: string; sampai?: string; kategori?: string; page?: number; perPage?: number }) =>
      get<Paginated<Biaya>>("/keuangan/biaya", params),
    simpanBiaya: (data: Omit<Biaya, "id">) => post<Envelope<Biaya>>("/keuangan/biaya", data),
    hapusBiaya: (id: string) => del<{ message: string }>(`/keuangan/biaya/${id}`),
  },
};
```

---

## 4. Pola pemakaian di halaman

`@tanstack/react-query` sudah ada di `package.json`, jadi pola paling ringkas:

```tsx
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { ApiError, api } from "@/lib/sigula-api";

function ProduksiPage() {
  const qc = useQueryClient();
  const [tanggal, setTanggal] = useState("");

  const { data, isLoading } = useQuery({
    queryKey: ["sesi", tanggal],
    queryFn: () => api.produksi.sesi({ tanggal, perPage: 100 }),
  });

  const selesaikan = useMutation({
    mutationFn: ({ id, kristal, brondol }: { id: string; kristal: number; brondol: number }) =>
      api.produksi.selesaikan(id, kristal, brondol),
    onSuccess: (res) => {
      toast.success(res.message!);
      // stok & gaji ikut berubah, jadi segarkan modul terkait
      qc.invalidateQueries({ queryKey: ["sesi"] });
      qc.invalidateQueries({ queryKey: ["stok"] });
      qc.invalidateQueries({ queryKey: ["penggajian"] });
      qc.invalidateQueries({ queryKey: ["dashboard"] });
    },
    onError: (e: ApiError) => toast.error(e.message),
  });

  const rows = data?.data ?? [];
  // ... render DataTable seperti sekarang
}
```

**Invalidasi yang perlu diingat** (efek antar modul dijaga server, tapi cache klien perlu tahu):

| Aksi | Query yang perlu di-invalidate |
|---|---|
| Simpan/batal pembelian | `pembelian`, `stok`, `dashboard`, `keuangan` |
| Selesaikan/batal sesi tungku | `sesi`, `stok`, `penggajian`, `dashboard`, `keuangan` |
| Simpan/batal penjualan | `penjualan`, `stok`, `dashboard`, `keuangan` |
| Bayar gaji | `penggajian`, `keuangan` |
| Ubah harga/tarif | `master`, `keuangan` |
| Stok opname | `stok`, `dashboard` |

---

## 5. Login & sidebar berbasis role

Ganti isi `src/routes/index.tsx` (halaman login) agar memanggil API:

```tsx
const submit = async (e: React.FormEvent) => {
  e.preventDefault();
  try {
    const res = await api.auth.login(email, password);
    token.set(res.data.token);
    toast.success(res.message!, { description: `Selamat datang, ${res.data.user.nama}.` });
    navigate({ to: "/dashboard" });
  } catch (err) {
    setError(err instanceof ApiError ? err.message : "Gagal masuk.");
  }
};
```

`GET /auth/me` mengembalikan `menu` (daftar slug modul yang boleh dibuka), jadi
`app-shell.tsx` bisa memfilter item sidebar:

```tsx
const { data } = useQuery({ queryKey: ["me"], queryFn: api.auth.me });
const menu = data?.data.menu ?? [];
const items = SEMUA_MENU.filter((item) => menu.includes(item.slug));
```

Server tetap menolak akses terlarang dengan 403 — penyembunyian menu hanya untuk kenyamanan.

---

## 6. Yang tetap dikerjakan frontend

- **Format Rupiah & tanggal** (`src/lib/format.ts`) — API mengirim angka mentah; khusus
  tanggal tersedia `tanggalLabel` bila ingin memakai versi server.
- **Kalkulasi dua arah di form penjualan** — subtotal diedit manual → harga per kg
  menyesuaikan, kilogram tetap, dan baris satunya tidak terpengaruh. Kirim `kg` + `harga`
  final ke API; server menghitung ulang subtotal & total agar tersimpan konsisten.
- **Preview cetak** kwitansi, invoice, dan slip gaji. Data siap pakai sudah disediakan
  server (`kwitansi`, `invoice`, dan endpoint `penggajian/slip/{id}`).

---

## 7. Urutan migrasi yang disarankan

1. Tambah `sigula-api.ts` + halaman login (token tersimpan) — **belum menyentuh store**.
2. Pindahkan modul baca-saja: Dashboard, Stok, Keuangan.
3. Pindahkan modul master: Petani, Master Harga & Tarif, Karyawan, Eksportir.
4. Pindahkan modul transaksi: Pembelian → Produksi → Penjualan → Penggajian.
5. Hapus `SigulaProvider`, `sigula-store.tsx`, dan `sigula-seed.ts` setelah semua halaman
   pindah. `sigula-types.ts` tetap dipakai sebagai tipe response.

Kerjakan per modul: selama masa transisi, halaman yang belum dipindah tetap jalan dengan
store lama karena keduanya tidak saling bergantung.
