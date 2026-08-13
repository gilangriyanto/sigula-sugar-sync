import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from "react";
import { addDays, mondayOf, monthKey } from "@/lib/format";
import { buildSeed, todayISO } from "@/lib/sigula-seed";
import {
  KATEGORI_STOK,
  type BarisGaji,
  type BarisJual,
  type Biaya,
  type Grade,
  type Karyawan,
  type Eksportir,
  type Pembelian,
  type Penjualan,
  type Petani,
  type SesiTungku,
  type SigulaState,
  type StokKategori,
  type StokMove,
  type Tarif,
} from "@/lib/sigula-types";

let counter = 0;
const uid = (pre: string) => `${pre}-n${Date.now().toString(36)}${(++counter).toString(36)}`;

interface Ctx {
  state: SigulaState;
  today: string;
  stok: Record<StokKategori, number>;
  namaPetani: (id: string) => string;
  namaKaryawan: (id: string) => string;
  namaEksportir: (id: string) => string;
  // petani
  addPetani: (p: Omit<Petani, "id">) => void;
  updatePetani: (id: string, p: Omit<Petani, "id">) => void;
  deletePetani: (id: string) => void;
  // master
  setHarga: (grade: Grade, harga: number, tanggal: string) => void;
  setTarif: (t: Partial<Tarif>) => void;
  addKaryawan: (k: Omit<Karyawan, "id">) => void;
  deleteKaryawan: (id: string) => void;
  addEksportir: (e: Omit<Eksportir, "id">) => void;
  deleteEksportir: (id: string) => void;
  // pembelian
  addPembelian: (p: Omit<Pembelian, "id" | "total">) => Pembelian;
  deletePembelian: (id: string) => void;
  // stok
  addOpname: (kategori: StokKategori, selisih: number, alasan: string, tanggal: string) => void;
  // produksi
  mulaiSesi: (s: Omit<SesiTungku, "id" | "status" | "kgKristal" | "kgBrondol">) => void;
  selesaikanSesi: (id: string, kgKristal: number, kgBrondol: number) => void;
  deleteSesi: (id: string) => void;
  // penjualan
  addPenjualan: (
    p: Omit<Penjualan, "id" | "total" | "noInvoice"> & { kristal: BarisJual | null; brondol: BarisJual | null },
  ) => Penjualan;
  deletePenjualan: (id: string) => void;
  // gaji
  payrollMinggu: (mondayISO: string) => BarisGaji[];
  bayarGaji: (karyawanId: string, mondayISO: string) => void;
  bayarSemuaGaji: (mondayISO: string) => void;
  // biaya
  addBiaya: (b: Omit<Biaya, "id">) => void;
  deleteBiaya: (id: string) => void;
}

const SigulaContext = createContext<Ctx | null>(null);

export function SigulaProvider({ children }: { children: ReactNode }) {
  const today = useMemo(() => todayISO(), []);
  const [state, setState] = useState<SigulaState>(() => buildSeed(todayISO()));

  const stok = useMemo(() => {
    const acc = Object.fromEntries(KATEGORI_STOK.map((k) => [k, 0])) as Record<StokKategori, number>;
    for (const m of state.stokMoves) {
      acc[m.kategori] += m.jenis === "Masuk" ? m.jumlah : -m.jumlah;
    }
    return acc;
  }, [state.stokMoves]);

  const namaPetani = useCallback(
    (id: string) => state.petani.find((p) => p.id === id)?.nama ?? "-",
    [state.petani],
  );
  const namaKaryawan = useCallback(
    (id: string) => state.karyawan.find((k) => k.id === id)?.nama ?? "-",
    [state.karyawan],
  );
  const namaEksportir = useCallback(
    (id: string) => state.eksportir.find((e) => e.id === id)?.nama ?? "-",
    [state.eksportir],
  );

  const addPetani: Ctx["addPetani"] = (p) =>
    setState((s) => ({ ...s, petani: [{ ...p, id: uid("p") }, ...s.petani] }));

  const updatePetani: Ctx["updatePetani"] = (id, p) =>
    setState((s) => ({ ...s, petani: s.petani.map((x) => (x.id === id ? { ...p, id } : x)) }));

  const deletePetani: Ctx["deletePetani"] = (id) =>
    setState((s) => ({ ...s, petani: s.petani.filter((x) => x.id !== id) }));

  const setHarga: Ctx["setHarga"] = (grade, harga, tanggal) =>
    setState((s) => ({
      ...s,
      hargaBeli: { ...s.hargaBeli, [grade]: harga },
      riwayatHarga: [
        { id: uid("rh"), grade, tanggal, hargaLama: s.hargaBeli[grade], hargaBaru: harga },
        ...s.riwayatHarga,
      ],
    }));

  const setTarif: Ctx["setTarif"] = (t) => setState((s) => ({ ...s, tarif: { ...s.tarif, ...t } }));

  const addKaryawan: Ctx["addKaryawan"] = (k) =>
    setState((s) => ({ ...s, karyawan: [...s.karyawan, { ...k, id: uid("k") }] }));
  const deleteKaryawan: Ctx["deleteKaryawan"] = (id) =>
    setState((s) => ({ ...s, karyawan: s.karyawan.filter((k) => k.id !== id) }));
  const addEksportir: Ctx["addEksportir"] = (e) =>
    setState((s) => ({ ...s, eksportir: [...s.eksportir, { ...e, id: uid("e") }] }));
  const deleteEksportir: Ctx["deleteEksportir"] = (id) =>
    setState((s) => ({ ...s, eksportir: s.eksportir.filter((e) => e.id !== id) }));

  const addPembelian: Ctx["addPembelian"] = (p) => {
    const trx: Pembelian = { ...p, id: uid("beli"), total: p.kg * p.harga };
    setState((s) => ({
      ...s,
      pembelian: [trx, ...s.pembelian],
      stokMoves: [
        {
          id: uid("sm"),
          tanggal: p.tanggal,
          jenis: "Masuk",
          kategori: p.grade,
          jumlah: p.kg,
          keterangan: `Pembelian dari ${s.petani.find((x) => x.id === p.petaniId)?.nama ?? "-"}`,
        },
        ...s.stokMoves,
      ],
    }));
    return trx;
  };

  const deletePembelian: Ctx["deletePembelian"] = (id) =>
    setState((s) => ({ ...s, pembelian: s.pembelian.filter((p) => p.id !== id) }));

  const addOpname: Ctx["addOpname"] = (kategori, selisih, alasan, tanggal) =>
    setState((s) => ({
      ...s,
      stokMoves: [
        {
          id: uid("sm"),
          tanggal,
          jenis: selisih >= 0 ? "Masuk" : "Keluar",
          kategori,
          jumlah: Math.abs(selisih),
          keterangan: `Stok opname: ${alasan}`,
        },
        ...s.stokMoves,
      ],
    }));

  const mulaiSesi: Ctx["mulaiSesi"] = (s0) =>
    setState((s) => ({
      ...s,
      sesi: [
        { ...s0, id: uid("sesi"), kgKristal: null, kgBrondol: null, status: "Sedang Diproses" },
        ...s.sesi,
      ],
    }));

  const selesaikanSesi: Ctx["selesaikanSesi"] = (id, kgKristal, kgBrondol) =>
    setState((s) => {
      const target = s.sesi.find((x) => x.id === id);
      if (!target) return s;
      const moves: StokMove[] = [
        {
          id: uid("sm"),
          tanggal: target.tanggal,
          jenis: "Keluar",
          kategori: target.grade,
          jumlah: target.kgBahan,
          keterangan: `Produksi tungku ${target.kodeTungku}`,
        },
        {
          id: uid("sm"),
          tanggal: target.tanggal,
          jenis: "Masuk",
          kategori: "Kristal",
          jumlah: kgKristal,
          keterangan: `Hasil tungku ${target.kodeTungku}`,
        },
        {
          id: uid("sm"),
          tanggal: target.tanggal,
          jenis: "Masuk",
          kategori: "Brondol",
          jumlah: kgBrondol,
          keterangan: `Hasil tungku ${target.kodeTungku}`,
        },
      ];
      return {
        ...s,
        sesi: s.sesi.map((x) => (x.id === id ? { ...x, kgKristal, kgBrondol, status: "Selesai" } : x)),
        stokMoves: [...moves, ...s.stokMoves],
      };
    });

  const deleteSesi: Ctx["deleteSesi"] = (id) =>
    setState((s) => ({ ...s, sesi: s.sesi.filter((x) => x.id !== id) }));

  const addPenjualan: Ctx["addPenjualan"] = (p) => {
    const total =
      (p.kristal ? p.kristal.kg * p.kristal.harga : 0) + (p.brondol ? p.brondol.kg * p.brondol.harga : 0);
    let created!: Penjualan;
    setState((s) => {
      created = {
        ...p,
        id: uid("jual"),
        noInvoice: `INV/${p.tanggal.slice(0, 4)}/${String(s.penjualan.length + 1).padStart(4, "0")}`,
        total,
      };
      const nama = s.eksportir.find((e) => e.id === p.eksportirId)?.nama ?? "-";
      const moves: StokMove[] = [];
      if (p.kristal)
        moves.push({
          id: uid("sm"),
          tanggal: p.tanggal,
          jenis: "Keluar",
          kategori: "Kristal",
          jumlah: p.kristal.kg,
          keterangan: `Penjualan ke ${nama}`,
        });
      if (p.brondol)
        moves.push({
          id: uid("sm"),
          tanggal: p.tanggal,
          jenis: "Keluar",
          kategori: "Brondol",
          jumlah: p.brondol.kg,
          keterangan: `Penjualan ke ${nama}`,
        });
      return { ...s, penjualan: [created, ...s.penjualan], stokMoves: [...moves, ...s.stokMoves] };
    });
    return created;
  };

  const deletePenjualan: Ctx["deletePenjualan"] = (id) =>
    setState((s) => ({ ...s, penjualan: s.penjualan.filter((p) => p.id !== id) }));

  const payrollMinggu = useCallback(
    (mondayISO: string): BarisGaji[] => {
      const senin = mondayOf(mondayISO);
      const jumat = addDays(senin, 4);
      const acc = new Map<string, { kristal: number; brondol: number; hari: Set<string> }>();
      for (const s of state.sesi) {
        if (s.tanggal < senin || s.tanggal > jumat) continue;
        if (s.status !== "Selesai") continue;
        const kristal = (s.kgKristal ?? 0) / 2;
        const brondol = (s.kgBrondol ?? 0) / 2;
        for (const kid of s.karyawanIds) {
          const cur = acc.get(kid) ?? { kristal: 0, brondol: 0, hari: new Set<string>() };
          cur.kristal += kristal;
          cur.brondol += brondol;
          cur.hari.add(s.tanggal);
          acc.set(kid, cur);
        }
      }
      const rows: BarisGaji[] = [];
      for (const k of state.karyawan) {
        const a = acc.get(k.id);
        if (!a) continue;
        const upahKristal = a.kristal * state.tarif.kristal;
        const upahBrondol = a.brondol * state.tarif.brondol;
        const uangMakan = a.hari.size * state.tarif.uangMakan;
        rows.push({
          karyawanId: k.id,
          nama: k.nama,
          kgKristal: a.kristal,
          kgBrondol: a.brondol,
          hariKerja: a.hari.size,
          upahKristal,
          upahBrondol,
          uangMakan,
          total: upahKristal + upahBrondol + uangMakan,
          dibayar: state.gajiDibayar.includes(`${k.id}|${senin}`),
        });
      }
      return rows.sort((a, b) => b.total - a.total);
    },
    [state.sesi, state.karyawan, state.tarif, state.gajiDibayar],
  );

  const bayarGaji: Ctx["bayarGaji"] = (karyawanId, mondayISO) =>
    setState((s) => {
      const key = `${karyawanId}|${mondayOf(mondayISO)}`;
      return s.gajiDibayar.includes(key) ? s : { ...s, gajiDibayar: [...s.gajiDibayar, key] };
    });

  const bayarSemuaGaji: Ctx["bayarSemuaGaji"] = (mondayISO) => {
    const rows = payrollMinggu(mondayISO);
    const senin = mondayOf(mondayISO);
    setState((s) => {
      const set = new Set(s.gajiDibayar);
      rows.forEach((r) => set.add(`${r.karyawanId}|${senin}`));
      return { ...s, gajiDibayar: [...set] };
    });
  };

  const addBiaya: Ctx["addBiaya"] = (b) =>
    setState((s) => ({ ...s, biaya: [{ ...b, id: uid("biaya") }, ...s.biaya] }));
  const deleteBiaya: Ctx["deleteBiaya"] = (id) =>
    setState((s) => ({ ...s, biaya: s.biaya.filter((b) => b.id !== id) }));

  const value: Ctx = {
    state,
    today,
    stok,
    namaPetani,
    namaKaryawan,
    namaEksportir,
    addPetani,
    updatePetani,
    deletePetani,
    setHarga,
    setTarif,
    addKaryawan,
    deleteKaryawan,
    addEksportir,
    deleteEksportir,
    addPembelian,
    deletePembelian,
    addOpname,
    mulaiSesi,
    selesaikanSesi,
    deleteSesi,
    addPenjualan,
    deletePenjualan,
    payrollMinggu,
    bayarGaji,
    bayarSemuaGaji,
    addBiaya,
    deleteBiaya,
  };

  return <SigulaContext.Provider value={value}>{children}</SigulaContext.Provider>;
}

export function useSigula() {
  const ctx = useContext(SigulaContext);
  if (!ctx) throw new Error("useSigula harus dipakai di dalam SigulaProvider");
  return ctx;
}

/** total gaji (termasuk uang makan) untuk semua sesi selesai pada 1 bulan */
export function gajiBulan(state: SigulaState, mk: string): number {
  let upah = 0;
  const hariPerKaryawan = new Map<string, Set<string>>();
  for (const s of state.sesi) {
    if (monthKey(s.tanggal) !== mk || s.status !== "Selesai") continue;
    upah += (s.kgKristal ?? 0) * state.tarif.kristal + (s.kgBrondol ?? 0) * state.tarif.brondol;
    for (const kid of s.karyawanIds) {
      const set = hariPerKaryawan.get(kid) ?? new Set<string>();
      set.add(s.tanggal);
      hariPerKaryawan.set(kid, set);
    }
  }
  let makan = 0;
  hariPerKaryawan.forEach((set) => (makan += set.size * state.tarif.uangMakan));
  return upah + makan;
}
