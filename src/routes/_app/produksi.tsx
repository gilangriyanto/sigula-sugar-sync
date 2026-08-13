import { useMemo, useState } from "react";
import { createFileRoute } from "@tanstack/react-router";
import { CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
import { CheckCircle2, Flame, Plus, Trash2 } from "lucide-react";
import { toast } from "sonner";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import {
  DataTable,
  EmptyState,
  PageHeader,
  SearchInput,
  StatCard,
  type Column,
} from "@/components/sigula/ui-bits";
import { addDays, angka, kg, tanggalPendek } from "@/lib/format";
import { GRADES, type Grade, type SesiTungku } from "@/lib/sigula-types";
import { useSigula } from "@/store/sigula-store";

export const Route = createFileRoute("/_app/produksi")({
  head: () => ({
    meta: [
      { title: "Produksi Sesi Tungku — SIGULA" },
      {
        name: "description",
        content:
          "Kelola sesi tungku dengan dua karyawan per tungku, catat hasil gula kristal & brondol, hitung rendemen, dan bagi hasil otomatis untuk penggajian.",
      },
      { property: "og:title", content: "Produksi Sesi Tungku — SIGULA" },
      { property: "og:description", content: "Sesi tungku paralel, rendemen otomatis, dan pembagian hasil dua karyawan." },
    ],
  }),
  component: ProduksiPage,
});

function rendemenOf(s: SesiTungku) {
  if (s.kgKristal === null || s.kgBrondol === null || !s.kgBahan) return null;
  return ((s.kgKristal + s.kgBrondol) / s.kgBahan) * 100;
}

function ProduksiPage() {
  const { state, stok, mulaiSesi, selesaikanSesi, deleteSesi, namaKaryawan, today } = useSigula();

  const [openMulai, setOpenMulai] = useState(false);
  const [selesai, setSelesai] = useState<SesiTungku | null>(null);
  const [q, setQ] = useState("");
  const [fTanggal, setFTanggal] = useState("");
  const [fStatus, setFStatus] = useState<"semua" | "Sedang Diproses" | "Selesai">("semua");

  const [form, setForm] = useState({
    tanggal: today,
    kodeTungku: "",
    grade: "NS 1" as Grade,
    kgBahan: "",
    k1: "",
    k2: "",
  });
  const [err, setErr] = useState<string | null>(null);

  const [hasil, setHasil] = useState({ kristal: "", brondol: "" });
  const [errHasil, setErrHasil] = useState<string | null>(null);

  const sesiHariIni = state.sesi.filter((s) => s.tanggal === today);
  const aktifHariIni = sesiHariIni.filter((s) => s.status === "Sedang Diproses").length;
  const produksiHariIni = sesiHariIni.reduce((a, s) => a + (s.kgKristal ?? 0) + (s.kgBrondol ?? 0), 0);

  const rows = useMemo(() => {
    const term = q.trim().toLowerCase();
    return state.sesi
      .filter((s) => (fTanggal ? s.tanggal === fTanggal : true))
      .filter((s) => (fStatus === "semua" ? true : s.status === fStatus))
      .filter((s) =>
        term
          ? s.kodeTungku.toLowerCase().includes(term) ||
            s.karyawanIds.some((k) => namaKaryawan(k).toLowerCase().includes(term))
          : true,
      )
      .slice(0, 400);
  }, [state.sesi, q, fTanggal, fStatus, namaKaryawan]);

  const trenRendemen = useMemo(() => {
    const out: { tanggal: string; rendemen: number }[] = [];
    for (let i = 13; i >= 0; i--) {
      const t = addDays(today, -i);
      const rs = state.sesi.filter((s) => s.tanggal === t && s.status === "Selesai");
      const bahan = rs.reduce((a, s) => a + s.kgBahan, 0);
      const hasilKg = rs.reduce((a, s) => a + (s.kgKristal ?? 0) + (s.kgBrondol ?? 0), 0);
      out.push({
        tanggal: tanggalPendek(t).replace(/ \d{4}$/, ""),
        rendemen: bahan ? Number(((hasilKg / bahan) * 100).toFixed(1)) : 0,
      });
    }
    return out;
  }, [state.sesi, today]);

  const bukaMulai = () => {
    const kodeTerpakai = state.sesi.filter((s) => s.tanggal === today).length + 1;
    setForm({
      tanggal: today,
      kodeTungku: `TGK-${String(kodeTerpakai).padStart(2, "0")}`,
      grade: "NS 1",
      kgBahan: "",
      k1: "",
      k2: "",
    });
    setErr(null);
    setOpenMulai(true);
  };

  const simpanMulai = () => {
    const kgNum = Number(form.kgBahan);
    if (!form.tanggal) return setErr("Tanggal wajib diisi.");
    if (!form.kodeTungku.trim()) return setErr("Kode tungku wajib diisi.");
    if (!form.kgBahan.trim() || Number.isNaN(kgNum) || kgNum <= 0)
      return setErr("Kg bahan mentah harus lebih dari 0.");
    if (kgNum > stok[form.grade])
      return setErr(`Stok bahan ${form.grade} hanya ${angka(stok[form.grade])} kg.`);
    if (!form.k1 || !form.k2) return setErr("Kedua slot karyawan wajib diisi.");
    if (form.k1 === form.k2) return setErr("Karyawan 1 dan Karyawan 2 tidak boleh orang yang sama.");
    mulaiSesi({
      tanggal: form.tanggal,
      kodeTungku: form.kodeTungku.trim(),
      grade: form.grade,
      kgBahan: kgNum,
      karyawanIds: [form.k1, form.k2],
    });
    toast.success("Sesi tungku dimulai", {
      description: `${form.kodeTungku} — ${namaKaryawan(form.k1)} & ${namaKaryawan(form.k2)}`,
    });
    setOpenMulai(false);
  };

  const kgKristalNum = Number(hasil.kristal) || 0;
  const kgBrondolNum = Number(hasil.brondol) || 0;
  const rendemenPreview = selesai?.kgBahan ? ((kgKristalNum + kgBrondolNum) / selesai.kgBahan) * 100 : 0;

  const simpanSelesai = () => {
    if (!selesai) return;
    if (!hasil.kristal.trim() && !hasil.brondol.trim()) return setErrHasil("Isi hasil produksi tungku ini.");
    if (kgKristalNum < 0 || kgBrondolNum < 0) return setErrHasil("Nilai tidak boleh negatif.");
    if (kgKristalNum + kgBrondolNum <= 0) return setErrHasil("Total hasil harus lebih dari 0.");
    selesaikanSesi(selesai.id, kgKristalNum, kgBrondolNum);
    toast.success(`Sesi ${selesai.kodeTungku} selesai`, {
      description: `Rendemen ${rendemenPreview.toFixed(1)}% · masing-masing karyawan tercatat ${angka(
        kgKristalNum / 2,
      )} kg kristal & ${angka(kgBrondolNum / 2)} kg brondol`,
    });
    setSelesai(null);
    setHasil({ kristal: "", brondol: "" });
    setErrHasil(null);
  };

  const cols: Column<SesiTungku>[] = [
    { key: "tgl", header: "Tanggal", sortValue: (r) => r.tanggal, cell: (r) => tanggalPendek(r.tanggal) },
    {
      key: "kode",
      header: "Kode Tungku",
      sortValue: (r) => r.kodeTungku,
      cell: (r) => <span className="font-medium">{r.kodeTungku}</span>,
    },
    { key: "grade", header: "Grade", sortValue: (r) => r.grade, cell: (r) => <Badge variant="secondary">{r.grade}</Badge> },
    {
      key: "bahan",
      header: "Kg Bahan",
      align: "right",
      sortValue: (r) => r.kgBahan,
      cell: (r) => `${angka(r.kgBahan)} kg`,
    },
    {
      key: "kar",
      header: "Karyawan Bertugas",
      cell: (r) => (
        <div className="text-xs">
          <p>{namaKaryawan(r.karyawanIds[0])}</p>
          <p className="text-muted-foreground">{namaKaryawan(r.karyawanIds[1])}</p>
        </div>
      ),
    },
    {
      key: "kristal",
      header: "Kg Kristal",
      align: "right",
      sortValue: (r) => r.kgKristal ?? -1,
      cell: (r) => (r.kgKristal === null ? "—" : `${angka(r.kgKristal)} kg`),
    },
    {
      key: "brondol",
      header: "Kg Brondol",
      align: "right",
      sortValue: (r) => r.kgBrondol ?? -1,
      cell: (r) => (r.kgBrondol === null ? "—" : `${angka(r.kgBrondol)} kg`),
    },
    {
      key: "rend",
      header: "Rendemen",
      align: "right",
      sortValue: (r) => rendemenOf(r) ?? -1,
      cell: (r) => {
        const v = rendemenOf(r);
        return v === null ? "—" : <span className="font-medium">{v.toFixed(1)}%</span>;
      },
    },
    {
      key: "status",
      header: "Status",
      sortValue: (r) => r.status,
      cell: (r) =>
        r.status === "Selesai" ? (
          <Badge className="bg-success/15 text-success hover:bg-success/15">Selesai</Badge>
        ) : (
          <Badge className="bg-warning/25 text-warning-foreground hover:bg-warning/25">Sedang Diproses</Badge>
        ),
    },
    {
      key: "aksi",
      header: "Aksi",
      align: "right",
      cell: (r) => (
        <div className="flex justify-end gap-1">
          {r.status === "Sedang Diproses" && (
            <Button
              size="sm"
              onClick={() => {
                setSelesai(r);
                setHasil({ kristal: "", brondol: "" });
                setErrHasil(null);
              }}
            >
              <CheckCircle2 className="mr-1 size-4" /> Selesaikan
            </Button>
          )}
          <Button
            variant="ghost"
            size="icon"
            aria-label="Hapus"
            onClick={() => {
              deleteSesi(r.id);
              toast.success("Sesi tungku dihapus", { description: r.kodeTungku });
            }}
          >
            <Trash2 className="size-4 text-destructive" />
          </Button>
        </div>
      ),
    },
  ];

  return (
    <>
      <PageHeader
        title="Produksi (Sesi Tungku)"
        subtitle="Setiap tungku dikerjakan tepat 2 karyawan; kristal & brondol keluar dari proses masak yang sama"
        action={
          <Button onClick={bukaMulai}>
            <Plus className="mr-2 size-4" /> Mulai Sesi Tungku Baru
          </Button>
        }
      />

      <div className="grid gap-4 sm:grid-cols-3">
        <StatCard
          label="Tungku Aktif Hari Ini"
          value={`${aktifHariIni} tungku`}
          icon={<Flame className="size-5" />}
          tone="warning"
          hint={`${sesiHariIni.length} sesi tercatat hari ini`}
        />
        <StatCard label="Produksi Hari Ini" value={kg(produksiHariIni)} tone="success" />
        <StatCard
          label="Stok Bahan Tersedia"
          value={kg(stok["NS 1"] + stok["NS 2"] + stok["Kecap"])}
          hint={`NS 1 ${angka(stok["NS 1"])} · NS 2 ${angka(stok["NS 2"])} · Kecap ${angka(stok["Kecap"])}`}
        />
      </div>

      <Card className="mt-6 shadow-card">
        <CardHeader>
          <CardTitle className="text-base">Tren Rendemen 14 Hari Terakhir</CardTitle>
        </CardHeader>
        <CardContent className="h-[220px]">
          <ResponsiveContainer width="100%" height="100%">
            <LineChart data={trenRendemen} margin={{ left: 8, right: 8 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" vertical={false} />
              <XAxis dataKey="tanggal" tickLine={false} axisLine={false} fontSize={11} />
              <YAxis domain={[80, 100]} tickLine={false} axisLine={false} fontSize={11} unit="%" />
              <Tooltip formatter={(v) => `${v}%`} />
              <Line
                type="monotone"
                dataKey="rendemen"
                name="Rendemen"
                stroke="var(--chart-1)"
                strokeWidth={3}
                dot={{ r: 3 }}
              />
            </LineChart>
          </ResponsiveContainer>
        </CardContent>
      </Card>

      <Card className="mt-6 overflow-hidden shadow-card">
        <CardHeader>
          <CardTitle className="text-base">Daftar Sesi Tungku</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4 px-0">
          <div className="flex flex-wrap items-end gap-3 px-4">
            <SearchInput value={q} onChange={setQ} placeholder="Cari kode tungku / karyawan..." />
            <div className="space-y-1">
              <Label className="text-xs">Tanggal</Label>
              <Input
                type="date"
                value={fTanggal}
                onChange={(e) => setFTanggal(e.target.value)}
                className="w-[170px]"
              />
            </div>
            <div className="space-y-1">
              <Label className="text-xs">Status</Label>
              <Select value={fStatus} onValueChange={(v: typeof fStatus) => setFStatus(v)}>
                <SelectTrigger className="w-[180px]">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="semua">Semua Status</SelectItem>
                  <SelectItem value="Sedang Diproses">Sedang Diproses</SelectItem>
                  <SelectItem value="Selesai">Selesai</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <Button variant="ghost" onClick={() => setFTanggal(today)}>
              Lihat hari ini
            </Button>
          </div>
          <DataTable
            rows={rows}
            columns={cols}
            rowKey={(r) => r.id}
            initialSort={{ key: "tgl", dir: "desc" }}
            empty={
              <EmptyState
                title="Belum ada sesi tungku"
                description="Mulai sesi tungku baru untuk mencatat produksi."
                action={
                  <Button onClick={bukaMulai}>
                    <Plus className="mr-2 size-4" /> Mulai Sesi Tungku Baru
                  </Button>
                }
              />
            }
          />
        </CardContent>
      </Card>

      {/* Modal mulai sesi */}
      <Dialog open={openMulai} onOpenChange={setOpenMulai}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle>Mulai Sesi Tungku Baru</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label>Tanggal</Label>
                <Input
                  type="date"
                  value={form.tanggal}
                  onChange={(e) => setForm({ ...form, tanggal: e.target.value })}
                />
              </div>
              <div className="space-y-2">
                <Label>Kode Tungku</Label>
                <Input
                  value={form.kodeTungku}
                  onChange={(e) => setForm({ ...form, kodeTungku: e.target.value })}
                />
              </div>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label>Grade Bahan Mentah</Label>
                <Select value={form.grade} onValueChange={(v: Grade) => setForm({ ...form, grade: v })}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {GRADES.map((g) => (
                      <SelectItem key={g} value={g}>
                        {g} — stok {angka(stok[g])} kg
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>Kg Bahan Mentah</Label>
                <Input
                  type="number"
                  min={0}
                  value={form.kgBahan}
                  onChange={(e) => setForm({ ...form, kgBahan: e.target.value })}
                  placeholder="0"
                />
              </div>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label>Karyawan 1 (wajib)</Label>
                <Select value={form.k1} onValueChange={(v) => setForm({ ...form, k1: v })}>
                  <SelectTrigger>
                    <SelectValue placeholder="Pilih karyawan" />
                  </SelectTrigger>
                  <SelectContent>
                    {state.karyawan.map((k) => (
                      <SelectItem key={k.id} value={k.id} disabled={k.id === form.k2}>
                        {k.nama}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>Karyawan 2 (wajib)</Label>
                <Select value={form.k2} onValueChange={(v) => setForm({ ...form, k2: v })}>
                  <SelectTrigger>
                    <SelectValue placeholder="Pilih karyawan" />
                  </SelectTrigger>
                  <SelectContent>
                    {state.karyawan.map((k) => (
                      <SelectItem key={k.id} value={k.id} disabled={k.id === form.k1}>
                        {k.nama}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>
            <p className="rounded-xl bg-cream px-4 py-3 text-xs text-muted-foreground">
              Hasil produksi tungku ini nanti dibagi rata otomatis ke kedua karyawan untuk perhitungan gaji.
            </p>
            {err && <p className="text-sm text-destructive">{err}</p>}
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setOpenMulai(false)}>
              Batal
            </Button>
            <Button onClick={simpanMulai}>Mulai Sesi</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Modal selesaikan sesi */}
      <Dialog open={selesai !== null} onOpenChange={(v) => !v && setSelesai(null)}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle>Selesaikan Sesi {selesai?.kodeTungku}</DialogTitle>
          </DialogHeader>
          {selesai && (
            <div className="space-y-4">
              <div className="rounded-xl bg-cream px-4 py-3 text-sm">
                <p>
                  Bahan mentah: <span className="font-medium">{selesai.grade}</span> ·{" "}
                  <span className="font-medium">{angka(selesai.kgBahan)} kg</span>
                </p>
                <p className="text-muted-foreground">
                  Karyawan: {namaKaryawan(selesai.karyawanIds[0])} & {namaKaryawan(selesai.karyawanIds[1])}
                </p>
              </div>
              <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                  <Label>Kg Kristal Total (tungku ini)</Label>
                  <Input
                    type="number"
                    min={0}
                    value={hasil.kristal}
                    onChange={(e) => setHasil({ ...hasil, kristal: e.target.value })}
                    placeholder="0"
                  />
                </div>
                <div className="space-y-2">
                  <Label>Kg Brondol Total (tungku ini)</Label>
                  <Input
                    type="number"
                    min={0}
                    value={hasil.brondol}
                    onChange={(e) => setHasil({ ...hasil, brondol: e.target.value })}
                    placeholder="0"
                  />
                </div>
              </div>
              <div className="rounded-xl border bg-card px-4 py-3">
                <p className="text-xs uppercase tracking-wide text-muted-foreground">Rendemen otomatis</p>
                <p className="text-2xl font-semibold">{rendemenPreview.toFixed(1)}%</p>
                <p className="text-xs text-muted-foreground">
                  ({angka(kgKristalNum)} + {angka(kgBrondolNum)}) ÷ {angka(selesai.kgBahan)} × 100%
                </p>
              </div>
              <div className="rounded-xl bg-secondary px-4 py-3 text-sm">
                <p className="mb-1 font-medium">Pembagian otomatis per karyawan (bagi 2)</p>
                <p className="text-muted-foreground">
                  {namaKaryawan(selesai.karyawanIds[0])} & {namaKaryawan(selesai.karyawanIds[1])} — masing-masing{" "}
                  {angka(selesai.kgBahan / 2)} kg bahan, {angka(kgKristalNum / 2)} kg kristal,{" "}
                  {angka(kgBrondolNum / 2)} kg brondol
                </p>
              </div>
              {errHasil && <p className="text-sm text-destructive">{errHasil}</p>}
            </div>
          )}
          <DialogFooter>
            <Button variant="outline" onClick={() => setSelesai(null)}>
              Batal
            </Button>
            <Button onClick={simpanSelesai}>Simpan & Selesaikan</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
