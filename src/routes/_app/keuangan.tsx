import { useMemo, useState } from "react";
import { createFileRoute } from "@tanstack/react-router";
import {
  Bar,
  BarChart,
  CartesianGrid,
  Legend,
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import { Download, Plus, Trash2 } from "lucide-react";
import { toast } from "sonner";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { DataTable, EmptyState, PageHeader, StatCard, type Column } from "@/components/sigula/ui-bits";
import { addDays, labelBulanSingkat, monthKey, rupiah, tanggalPendek } from "@/lib/format";
import type { Biaya, KategoriBiaya } from "@/lib/sigula-types";
import { gajiBulan, useSigula } from "@/store/sigula-store";

export const Route = createFileRoute("/_app/keuangan")({
  head: () => ({
    meta: [
      { title: "Keuangan & Laba Rugi — SIGULA" },
      {
        name: "description",
        content:
          "Laporan laba rugi: pendapatan penjualan, HPP pembelian bahan dan gaji karyawan, biaya operasional, margin keuntungan, dan tren 6 bulan.",
      },
      { property: "og:title", content: "Keuangan & Laba Rugi — SIGULA" },
      { property: "og:description", content: "Analisis pendapatan, biaya, dan laba bersih pengadaan gula." },
    ],
  }),
  component: KeuanganPage,
});

const KATEGORI: KategoriBiaya[] = ["Listrik", "Transport", "Sewa", "Lainnya"];

function KeuanganPage() {
  const { state, today, addBiaya, deleteBiaya } = useSigula();
  const [periode, setPeriode] = useState<"ini" | "lalu" | "custom">("ini");
  const [dari, setDari] = useState(addDays(today, -30));
  const [sampai, setSampai] = useState(today);
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState({
    tanggal: today,
    keterangan: "",
    kategori: "Listrik" as KategoriBiaya,
    jumlah: "",
  });
  const [err, setErr] = useState<string | null>(null);

  const range = useMemo(() => {
    const [y, m] = monthKey(today).split("-").map(Number);
    if (periode === "ini") {
      const start = `${monthKey(today)}-01`;
      return { start, end: today };
    }
    if (periode === "lalu") {
      const d = new Date(y!, m! - 2, 1);
      const mk = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`;
      const last = new Date(y!, m! - 1, 0).getDate();
      return { start: `${mk}-01`, end: `${mk}-${last}` };
    }
    return { start: dari, end: sampai };
  }, [periode, today, dari, sampai]);

  const inRange = (iso: string) => iso >= range.start && iso <= range.end;

  const ringkasan = useMemo(() => {
    const pendapatan = state.penjualan.filter((p) => inRange(p.tanggal)).reduce((a, p) => a + p.total, 0);
    const bahan = state.pembelian.filter((p) => inRange(p.tanggal)).reduce((a, p) => a + p.total, 0);
    const hariPer = new Map<string, Set<string>>();
    let upah = 0;
    state.sesi
      .filter((s) => s.status === "Selesai" && inRange(s.tanggal))
      .forEach((s) => {
        upah += (s.kgKristal ?? 0) * state.tarif.kristal + (s.kgBrondol ?? 0) * state.tarif.brondol;
        s.karyawanIds.forEach((k) => {
          const set = hariPer.get(k) ?? new Set<string>();
          set.add(s.tanggal);
          hariPer.set(k, set);
        });
      });
    let makan = 0;
    hariPer.forEach((set) => (makan += set.size * state.tarif.uangMakan));
    const gaji = upah + makan;
    const ops = state.biaya.filter((b) => inRange(b.tanggal)).reduce((a, b) => a + b.jumlah, 0);
    const hpp = bahan + gaji;
    return { pendapatan, bahan, gaji, hpp, ops, laba: pendapatan - hpp - ops };
  }, [state, range]);

  const chart = useMemo(() => {
    const [y, m] = monthKey(today).split("-").map(Number);
    const out: { bulan: string; pendapatan: number; biaya: number; margin: number }[] = [];
    for (let i = 5; i >= 0; i--) {
      const d = new Date(y!, m! - 1 - i, 1);
      const mk = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`;
      const pendapatan = state.penjualan
        .filter((p) => monthKey(p.tanggal) === mk)
        .reduce((a, p) => a + p.total, 0);
      const bahan = state.pembelian.filter((p) => monthKey(p.tanggal) === mk).reduce((a, p) => a + p.total, 0);
      const ops = state.biaya.filter((b) => monthKey(b.tanggal) === mk).reduce((a, b) => a + b.jumlah, 0);
      const biaya = bahan + gajiBulan(state, mk) + ops;
      out.push({
        bulan: labelBulanSingkat(mk),
        pendapatan,
        biaya,
        margin: pendapatan ? Number((((pendapatan - biaya) / pendapatan) * 100).toFixed(1)) : 0,
      });
    }
    return out;
  }, [state, today]);

  const biayaRows = useMemo(
    () => state.biaya.filter((b) => inRange(b.tanggal)),
    [state.biaya, range],
  );

  const simpan = () => {
    const n = Number(form.jumlah);
    if (!form.keterangan.trim()) return setErr("Keterangan wajib diisi.");
    if (!form.jumlah.trim() || Number.isNaN(n) || n <= 0) return setErr("Jumlah harus lebih dari 0.");
    if (!form.tanggal) return setErr("Tanggal wajib diisi.");
    addBiaya({ tanggal: form.tanggal, keterangan: form.keterangan.trim(), kategori: form.kategori, jumlah: n });
    toast.success("Biaya operasional ditambahkan", { description: `${form.keterangan} — ${rupiah(n)}` });
    setOpen(false);
    setForm({ tanggal: today, keterangan: "", kategori: "Listrik", jumlah: "" });
    setErr(null);
  };

  const cols: Column<Biaya>[] = [
    { key: "tgl", header: "Tanggal", sortValue: (r) => r.tanggal, cell: (r) => tanggalPendek(r.tanggal) },
    { key: "ket", header: "Keterangan", sortValue: (r) => r.keterangan, cell: (r) => r.keterangan },
    { key: "kat", header: "Kategori", sortValue: (r) => r.kategori, cell: (r) => <Badge variant="secondary">{r.kategori}</Badge> },
    { key: "jml", header: "Jumlah", align: "right", sortValue: (r) => r.jumlah, cell: (r) => <span className="font-medium">{rupiah(r.jumlah)}</span> },
    {
      key: "aksi",
      header: "Aksi",
      align: "right",
      cell: (r) => (
        <Button
          variant="ghost"
          size="icon"
          aria-label="Hapus"
          onClick={() => {
            deleteBiaya(r.id);
            toast.success("Biaya dihapus", { description: r.keterangan });
          }}
        >
          <Trash2 className="size-4 text-destructive" />
        </Button>
      ),
    },
  ];

  return (
    <>
      <PageHeader
        title="Keuangan & Laporan Laba Rugi"
        subtitle={`Periode ${tanggalPendek(range.start)} — ${tanggalPendek(range.end)}`}
        action={
          <Button variant="outline" onClick={() => toast.success("Laporan siap diunduh (mode demo)")}>
            <Download className="mr-2 size-4" /> Export Laporan
          </Button>
        }
      />

      <Card className="mb-4 shadow-card">
        <CardContent className="flex flex-wrap items-end gap-3 p-4">
          <div className="space-y-1">
            <Label className="text-xs">Periode</Label>
            <Select value={periode} onValueChange={(v: "ini" | "lalu" | "custom") => setPeriode(v)}>
              <SelectTrigger className="w-[180px]">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="ini">Bulan Ini</SelectItem>
                <SelectItem value="lalu">Bulan Lalu</SelectItem>
                <SelectItem value="custom">Custom Range</SelectItem>
              </SelectContent>
            </Select>
          </div>
          {periode === "custom" && (
            <>
              <div className="space-y-1">
                <Label className="text-xs">Dari</Label>
                <Input type="date" value={dari} onChange={(e) => setDari(e.target.value)} className="w-[170px]" />
              </div>
              <div className="space-y-1">
                <Label className="text-xs">Sampai</Label>
                <Input type="date" value={sampai} onChange={(e) => setSampai(e.target.value)} className="w-[170px]" />
              </div>
            </>
          )}
        </CardContent>
      </Card>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Pendapatan" value={rupiah(ringkasan.pendapatan)} tone="primary" />
        <StatCard
          label="Total HPP"
          value={rupiah(ringkasan.hpp)}
          hint={`Bahan ${rupiah(ringkasan.bahan)} · Gaji ${rupiah(ringkasan.gaji)}`}
        />
        <StatCard label="Biaya Operasional Lain" value={rupiah(ringkasan.ops)} />
        <StatCard
          label="Laba Bersih"
          value={<span className={ringkasan.laba >= 0 ? "text-success" : "text-destructive"}>{rupiah(ringkasan.laba)}</span>}
          tone={ringkasan.laba >= 0 ? "success" : "warning"}
          hint={
            ringkasan.pendapatan
              ? `Margin ${((ringkasan.laba / ringkasan.pendapatan) * 100).toFixed(1)}%`
              : "Belum ada pendapatan"
          }
        />
      </div>

      <div className="mt-6 grid gap-4 lg:grid-cols-2">
        <Card className="shadow-card">
          <CardHeader>
            <CardTitle className="text-base">Pendapatan vs Biaya per Bulan</CardTitle>
          </CardHeader>
          <CardContent className="h-[280px]">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={chart} margin={{ left: 8, right: 8 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" vertical={false} />
                <XAxis dataKey="bulan" tickLine={false} axisLine={false} fontSize={12} />
                <YAxis tickFormatter={(v: number) => `${Math.round(v / 1_000_000)}jt`} tickLine={false} axisLine={false} fontSize={12} />
                <Tooltip formatter={(v) => rupiah(Number(v))} />
                <Legend />
                <Bar dataKey="pendapatan" name="Pendapatan" fill="var(--chart-1)" radius={[6, 6, 0, 0]} />
                <Bar dataKey="biaya" name="Total Biaya" fill="var(--chart-4)" radius={[6, 6, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>

        <Card className="shadow-card">
          <CardHeader>
            <CardTitle className="text-base">Tren Margin Keuntungan (%)</CardTitle>
          </CardHeader>
          <CardContent className="h-[280px]">
            <ResponsiveContainer width="100%" height="100%">
              <LineChart data={chart} margin={{ left: 8, right: 8 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" vertical={false} />
                <XAxis dataKey="bulan" tickLine={false} axisLine={false} fontSize={12} />
                <YAxis tickLine={false} axisLine={false} fontSize={12} unit="%" />
                <Tooltip formatter={(v) => `${v}%`} />
                <Line type="monotone" dataKey="margin" name="Margin" stroke="var(--chart-3)" strokeWidth={3} dot={{ r: 4 }} />
              </LineChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>
      </div>

      <Card className="mt-6 overflow-hidden shadow-card">
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle className="text-base">Biaya Operasional Lain-lain</CardTitle>
          <Button size="sm" onClick={() => setOpen(true)}>
            <Plus className="mr-2 size-4" /> Tambah Biaya
          </Button>
        </CardHeader>
        <CardContent className="px-0">
          <DataTable
            rows={biayaRows}
            columns={cols}
            rowKey={(r) => r.id}
            initialSort={{ key: "tgl", dir: "desc" }}
            empty={<EmptyState title="Belum ada biaya" description="Tambahkan biaya operasional pada periode ini." />}
          />
        </CardContent>
      </Card>

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Tambah Biaya Operasional</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-2">
              <Label>Tanggal</Label>
              <Input type="date" value={form.tanggal} onChange={(e) => setForm({ ...form, tanggal: e.target.value })} />
            </div>
            <div className="space-y-2">
              <Label>Keterangan</Label>
              <Input
                value={form.keterangan}
                onChange={(e) => setForm({ ...form, keterangan: e.target.value })}
                placeholder="Contoh: tagihan listrik pabrik"
              />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label>Kategori</Label>
                <Select value={form.kategori} onValueChange={(v: KategoriBiaya) => setForm({ ...form, kategori: v })}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {KATEGORI.map((k) => (
                      <SelectItem key={k} value={k}>
                        {k}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>Jumlah (Rp)</Label>
                <Input type="number" min={0} value={form.jumlah} onChange={(e) => setForm({ ...form, jumlah: e.target.value })} />
              </div>
            </div>
            {err && <p className="text-sm text-destructive">{err}</p>}
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setOpen(false)}>
              Batal
            </Button>
            <Button onClick={simpan}>Simpan Biaya</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
