import { useMemo } from "react";
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
import { Boxes, Candy, Factory, Package, TrendingUp, Wheat } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, PageHeader, ProgressCircle, StatCard, type Column } from "@/components/sigula/ui-bits";
import { angka, kg, labelBulanSingkat, monthKey, rupiah, tanggalPendek } from "@/lib/format";
import { gajiBulan, useSigula } from "@/store/sigula-store";

export const Route = createFileRoute("/_app/dashboard")({
  head: () => ({
    meta: [
      { title: "Dashboard Operasional — SIGULA" },
      {
        name: "description",
        content:
          "Ringkasan stok bahan mentah, gula kristal & brondol, produksi tungku hari ini, tren penjualan, dan estimasi keuntungan bulan ini.",
      },
      { property: "og:title", content: "Dashboard Operasional — SIGULA" },
      {
        property: "og:description",
        content: "Pantau stok, produksi, rendemen, dan keuntungan pengadaan gula dalam satu tampilan.",
      },
    ],
  }),
  component: DashboardPage,
});

interface Aktivitas {
  id: string;
  tanggal: string;
  modul: string;
  keterangan: string;
  nilai: string;
}

function DashboardPage() {
  const { state, stok, today, namaPetani, namaEksportir } = useSigula();
  const mk = monthKey(today);

  const sesiHariIni = state.sesi.filter((s) => s.tanggal === today);
  const produksiHariIni = sesiHariIni.reduce((a, s) => a + (s.kgKristal ?? 0) + (s.kgBrondol ?? 0), 0);
  const tungkuAktif = sesiHariIni.filter((s) => s.status === "Sedang Diproses").length;

  const bulanIni = useMemo(() => {
    const pendapatan = state.penjualan
      .filter((p) => monthKey(p.tanggal) === mk)
      .reduce((a, p) => a + p.total, 0);
    const bahan = state.pembelian.filter((p) => monthKey(p.tanggal) === mk).reduce((a, p) => a + p.total, 0);
    const gaji = gajiBulan(state, mk);
    const ops = state.biaya.filter((b) => monthKey(b.tanggal) === mk).reduce((a, b) => a + b.jumlah, 0);
    return { pendapatan, laba: pendapatan - bahan - gaji - ops };
  }, [state, mk]);

  const rendemenBulan = useMemo(() => {
    const rows = state.sesi.filter((s) => monthKey(s.tanggal) === mk && s.status === "Selesai");
    const bahan = rows.reduce((a, s) => a + s.kgBahan, 0);
    const hasil = rows.reduce((a, s) => a + (s.kgKristal ?? 0) + (s.kgBrondol ?? 0), 0);
    return bahan ? (hasil / bahan) * 100 : 0;
  }, [state.sesi, mk]);

  const monthKeys = useMemo(() => {
    const keys: string[] = [];
    const [y, m] = mk.split("-").map(Number);
    for (let i = 5; i >= 0; i--) {
      const d = new Date(y!, m! - 1 - i, 1);
      keys.push(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`);
    }
    return keys;
  }, [mk]);

  const chartData = useMemo(
    () =>
      monthKeys.map((k) => ({
        bulan: labelBulanSingkat(k),
        penjualan: state.penjualan.filter((p) => monthKey(p.tanggal) === k).reduce((a, p) => a + p.total, 0),
        pembelian: state.pembelian.filter((p) => monthKey(p.tanggal) === k).reduce((a, p) => a + p.total, 0),
      })),
    [monthKeys, state.penjualan, state.pembelian],
  );

  const aktivitas = useMemo<Aktivitas[]>(() => {
    const items: Aktivitas[] = [
      ...state.pembelian.slice(0, 6).map((p) => ({
        id: "b" + p.id,
        tanggal: p.tanggal,
        modul: "Pembelian",
        keterangan: `${namaPetani(p.petaniId)} — ${p.grade} ${angka(p.kg)} kg`,
        nilai: rupiah(p.total),
      })),
      ...state.penjualan.slice(0, 6).map((p) => ({
        id: "j" + p.id,
        tanggal: p.tanggal,
        modul: "Penjualan",
        keterangan: `${namaEksportir(p.eksportirId)} — ${p.noInvoice}`,
        nilai: rupiah(p.total),
      })),
      ...state.sesi.slice(0, 6).map((s) => ({
        id: "s" + s.id,
        tanggal: s.tanggal,
        modul: "Produksi",
        keterangan: `${s.kodeTungku} — ${s.grade} ${angka(s.kgBahan)} kg (${s.status})`,
        nilai: s.kgKristal !== null ? `${angka(s.kgKristal)} kg kristal` : "-",
      })),
    ];
    return items.sort((a, b) => (a.tanggal < b.tanggal ? 1 : -1)).slice(0, 5);
  }, [state, namaPetani, namaEksportir]);

  const cols: Column<Aktivitas>[] = [
    { key: "tanggal", header: "Tanggal", cell: (r) => tanggalPendek(r.tanggal) },
    {
      key: "modul",
      header: "Modul",
      cell: (r) => <Badge variant="secondary">{r.modul}</Badge>,
    },
    { key: "ket", header: "Keterangan", cell: (r) => r.keterangan },
    { key: "nilai", header: "Nilai", align: "right", cell: (r) => r.nilai },
  ];

  const totalBahan = stok["NS 1"] + stok["NS 2"] + stok["Kecap"];

  return (
    <>
      <PageHeader
        title="Dashboard"
        subtitle={`Ringkasan operasional per ${tanggalPendek(today)}`}
      />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <StatCard
          label="Total Stok Bahan Mentah"
          value={kg(totalBahan)}
          icon={<Wheat className="size-5" />}
          tone="primary"
          hint={
            <span className="flex flex-wrap gap-x-3">
              <span>NS 1: {angka(stok["NS 1"])} kg</span>
              <span>NS 2: {angka(stok["NS 2"])} kg</span>
              <span>Kecap: {angka(stok["Kecap"])} kg</span>
            </span>
          }
        />
        <StatCard
          label="Stok Gula Kristal"
          value={kg(stok["Kristal"])}
          icon={<Package className="size-5" />}
          tone="success"
          hint="Produk jadi siap jual"
        />
        <StatCard
          label="Stok Gula Brondol"
          value={kg(stok["Brondol"])}
          icon={<Candy className="size-5" />}
          tone="warning"
          hint="Hasil sampingan proses masak"
        />
        <StatCard
          label="Total Produksi Hari Ini"
          value={kg(produksiHariIni)}
          icon={<Factory className="size-5" />}
          hint={`${sesiHariIni.length} sesi tungku tercatat hari ini`}
        />
        <StatCard
          label="Estimasi Keuntungan Bulan Ini"
          value={rupiah(bulanIni.laba)}
          icon={<TrendingUp className="size-5" />}
          tone={bulanIni.laba >= 0 ? "success" : "warning"}
          hint={`Pendapatan ${rupiah(bulanIni.pendapatan)}`}
        />
        <StatCard
          label="Sesi Tungku Aktif Hari Ini"
          value={`${tungkuAktif} tungku`}
          icon={<Boxes className="size-5" />}
          tone="warning"
          hint="Berstatus sedang diproses"
        />
      </div>

      <div className="mt-6 grid gap-4 lg:grid-cols-3">
        <Card className="shadow-card lg:col-span-2">
          <CardHeader>
            <CardTitle className="text-base">Tren Penjualan 6 Bulan Terakhir</CardTitle>
          </CardHeader>
          <CardContent className="h-[280px]">
            <ResponsiveContainer width="100%" height="100%">
              <LineChart data={chartData} margin={{ left: 8, right: 8 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" vertical={false} />
                <XAxis dataKey="bulan" tickLine={false} axisLine={false} fontSize={12} />
                <YAxis
                  tickFormatter={(v: number) => `${Math.round(v / 1_000_000)}jt`}
                  tickLine={false}
                  axisLine={false}
                  fontSize={12}
                />
                <Tooltip formatter={(v) => rupiah(Number(v))} />
                <Line
                  type="monotone"
                  dataKey="penjualan"
                  name="Penjualan"
                  stroke="var(--chart-1)"
                  strokeWidth={3}
                  dot={{ r: 4 }}
                />
              </LineChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>

        <Card className="shadow-card">
          <CardHeader>
            <CardTitle className="text-base">Rendemen Rata-rata Bulan Ini</CardTitle>
          </CardHeader>
          <CardContent className="flex flex-col items-center gap-2">
            <ProgressCircle value={rendemenBulan} label="rendemen" />
            <p className="text-center text-xs text-muted-foreground">
              (Kg Kristal + Kg Brondol) ÷ Kg Bahan Mentah
            </p>
          </CardContent>
        </Card>
      </div>

      <div className="mt-4 grid gap-4 lg:grid-cols-2">
        <Card className="shadow-card">
          <CardHeader>
            <CardTitle className="text-base">Pembelian Bahan vs Penjualan Produk</CardTitle>
          </CardHeader>
          <CardContent className="h-[300px]">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={chartData} margin={{ left: 8, right: 8 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" vertical={false} />
                <XAxis dataKey="bulan" tickLine={false} axisLine={false} fontSize={12} />
                <YAxis
                  tickFormatter={(v: number) => `${Math.round(v / 1_000_000)}jt`}
                  tickLine={false}
                  axisLine={false}
                  fontSize={12}
                />
                <Tooltip formatter={(v) => rupiah(Number(v))} />
                <Legend />
                <Bar dataKey="pembelian" name="Pembelian Bahan" fill="var(--chart-4)" radius={[6, 6, 0, 0]} />
                <Bar dataKey="penjualan" name="Penjualan Produk" fill="var(--chart-2)" radius={[6, 6, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>

        <Card className="overflow-hidden shadow-card">
          <CardHeader>
            <CardTitle className="text-base">Aktivitas Terbaru</CardTitle>
          </CardHeader>
          <CardContent className="px-0 pb-0">
            <DataTable rows={aktivitas} columns={cols} rowKey={(r) => r.id} />
          </CardContent>
        </Card>
      </div>
    </>
  );
}
