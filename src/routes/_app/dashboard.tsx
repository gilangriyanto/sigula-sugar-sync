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
import { Skeleton } from "@/components/ui/skeleton";
import {
  DataTable,
  PageHeader,
  ProgressCircle,
  StatCard,
  type Column,
} from "@/components/sigula/ui-bits";
import { angka, kg, rupiah, tanggalPendek } from "@/lib/format";
import type { DashboardAktivitas } from "@/lib/api/dashboard";
import { useDashboard } from "@/hooks/use-dashboard";

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
        content:
          "Pantau stok, produksi, rendemen, dan keuntungan pengadaan gula dalam satu tampilan.",
      },
    ],
  }),
  component: DashboardPage,
});

const aktivitasCols: Column<DashboardAktivitas>[] = [
  { key: "tanggal", header: "Tanggal", cell: (r) => tanggalPendek(r.tanggal) },
  {
    key: "modul",
    header: "Modul",
    cell: (r) => <Badge variant="secondary">{r.modul}</Badge>,
  },
  { key: "ket", header: "Keterangan", cell: (r) => r.keterangan },
  { key: "nilai", header: "Nilai", align: "right", cell: (r) => r.nilai ?? "-" },
];

function DashboardSkeleton() {
  return (
    <>
      <PageHeader title="Dashboard" subtitle="Memuat ringkasan operasional…" />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        {Array.from({ length: 6 }).map((_, i) => (
          <Card key={i} className="shadow-card">
            <CardContent className="space-y-3 p-5">
              <Skeleton className="size-10 rounded-xl" />
              <Skeleton className="h-3 w-24" />
              <Skeleton className="h-7 w-32" />
              <Skeleton className="h-3 w-40" />
            </CardContent>
          </Card>
        ))}
      </div>

      <div className="mt-6 grid gap-4 lg:grid-cols-3">
        <Card className="shadow-card lg:col-span-2">
          <CardHeader>
            <Skeleton className="h-5 w-56" />
          </CardHeader>
          <CardContent className="h-[280px]">
            <Skeleton className="h-full w-full" />
          </CardContent>
        </Card>
        <Card className="shadow-card">
          <CardHeader>
            <Skeleton className="h-5 w-48" />
          </CardHeader>
          <CardContent className="flex items-center justify-center py-6">
            <Skeleton className="size-36 rounded-full" />
          </CardContent>
        </Card>
      </div>

      <div className="mt-4 grid gap-4 lg:grid-cols-2">
        <Card className="shadow-card">
          <CardHeader>
            <Skeleton className="h-5 w-64" />
          </CardHeader>
          <CardContent className="h-[300px]">
            <Skeleton className="h-full w-full" />
          </CardContent>
        </Card>
        <Card className="shadow-card">
          <CardHeader>
            <Skeleton className="h-5 w-40" />
          </CardHeader>
          <CardContent className="space-y-3 p-5">
            {Array.from({ length: 5 }).map((_, i) => (
              <Skeleton key={i} className="h-8 w-full" />
            ))}
          </CardContent>
        </Card>
      </div>
    </>
  );
}

function DashboardPage() {
  const { data, isLoading, isError } = useDashboard();

  if (isLoading) return <DashboardSkeleton />;

  if (isError || !data) {
    return (
      <>
        <PageHeader title="Dashboard" subtitle="Ringkasan operasional" />
        <Card className="shadow-card">
          <CardContent className="py-10 text-center text-sm text-destructive">
            Gagal memuat ringkasan dashboard. Coba muat ulang halaman.
          </CardContent>
        </Card>
      </>
    );
  }

  const { stok, produksiHariIni, rendemenBulanIni, keuangan, tren, aktivitasTerbaru } = data;

  return (
    <>
      <PageHeader
        title="Dashboard"
        subtitle={`Ringkasan operasional per ${tanggalPendek(data.tanggal)}`}
      />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <StatCard
          label="Total Stok Bahan Mentah"
          value={kg(stok.bahanMentah.total)}
          icon={<Wheat className="size-5" />}
          tone="primary"
          hint={
            <span className="flex flex-wrap gap-x-3">
              <span>NS 1: {angka(stok.bahanMentah["NS 1"])} kg</span>
              <span>NS 2: {angka(stok.bahanMentah["NS 2"])} kg</span>
              <span>Kecap: {angka(stok.bahanMentah["Kecap"])} kg</span>
            </span>
          }
        />
        <StatCard
          label="Stok Gula Kristal"
          value={kg(stok.kristal)}
          icon={<Package className="size-5" />}
          tone="success"
          hint="Produk jadi siap jual"
        />
        <StatCard
          label="Stok Gula Brondol"
          value={kg(stok.brondol)}
          icon={<Candy className="size-5" />}
          tone="warning"
          hint="Hasil sampingan proses masak"
        />
        <StatCard
          label="Total Produksi Hari Ini"
          value={kg(produksiHariIni.totalProduksi)}
          icon={<Factory className="size-5" />}
          hint={
            produksiHariIni.rendemen !== null
              ? `${produksiHariIni.jumlahSesi} sesi tungku · rendemen ${angka(produksiHariIni.rendemen, 1)}%`
              : `${produksiHariIni.jumlahSesi} sesi tungku tercatat hari ini`
          }
        />
        {keuangan && (
          <StatCard
            label="Laba Bersih Bulan Ini"
            value={rupiah(keuangan.labaBulanIni)}
            icon={<TrendingUp className="size-5" />}
            tone={keuangan.labaBulanIni >= 0 ? "success" : "warning"}
            hint={`Pendapatan ${rupiah(keuangan.pendapatanBulanIni)} · Margin ${angka(keuangan.margin, 1)}%`}
          />
        )}
        <StatCard
          label="Sesi Tungku Aktif Hari Ini"
          value={`${produksiHariIni.tungkuAktif} tungku`}
          icon={<Boxes className="size-5" />}
          tone="warning"
          hint="Berstatus sedang diproses"
        />
      </div>

      <div className={`mt-6 grid gap-4 ${tren ? "lg:grid-cols-3" : ""}`}>
        {tren && (
          <Card className="shadow-card lg:col-span-2">
            <CardHeader>
              <CardTitle className="text-base">Tren Pendapatan 6 Bulan Terakhir</CardTitle>
            </CardHeader>
            <CardContent className="h-[280px]">
              <ResponsiveContainer width="100%" height="100%">
                <LineChart data={tren} margin={{ left: 8, right: 8 }}>
                  <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" vertical={false} />
                  <XAxis dataKey="label" tickLine={false} axisLine={false} fontSize={12} />
                  <YAxis
                    tickFormatter={(v: number) => `${Math.round(v / 1_000_000)}jt`}
                    tickLine={false}
                    axisLine={false}
                    fontSize={12}
                  />
                  <Tooltip formatter={(v) => rupiah(Number(v))} />
                  <Line
                    type="monotone"
                    dataKey="pendapatan"
                    name="Pendapatan"
                    stroke="var(--chart-1)"
                    strokeWidth={3}
                    dot={{ r: 4 }}
                  />
                </LineChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>
        )}

        <Card className="shadow-card">
          <CardHeader>
            <CardTitle className="text-base">Rendemen Rata-rata Bulan Ini</CardTitle>
          </CardHeader>
          <CardContent className="flex flex-col items-center gap-2">
            <ProgressCircle value={rendemenBulanIni} label="rendemen" />
            <p className="text-center text-xs text-muted-foreground">
              (Kg Kristal + Kg Brondol) ÷ Kg Bahan Mentah
            </p>
          </CardContent>
        </Card>
      </div>

      <div className={`mt-4 grid gap-4 ${tren ? "lg:grid-cols-2" : ""}`}>
        {tren && (
          <Card className="shadow-card">
            <CardHeader>
              <CardTitle className="text-base">Pembelian Bahan vs Penjualan Produk</CardTitle>
            </CardHeader>
            <CardContent className="h-[300px]">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={tren} margin={{ left: 8, right: 8 }}>
                  <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" vertical={false} />
                  <XAxis dataKey="label" tickLine={false} axisLine={false} fontSize={12} />
                  <YAxis
                    tickFormatter={(v: number) => `${Math.round(v / 1_000_000)}jt`}
                    tickLine={false}
                    axisLine={false}
                    fontSize={12}
                  />
                  <Tooltip formatter={(v) => rupiah(Number(v))} />
                  <Legend />
                  <Bar
                    dataKey="pembelian"
                    name="Pembelian Bahan"
                    fill="var(--chart-4)"
                    radius={[6, 6, 0, 0]}
                  />
                  <Bar
                    dataKey="pendapatan"
                    name="Penjualan Produk"
                    fill="var(--chart-2)"
                    radius={[6, 6, 0, 0]}
                  />
                </BarChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>
        )}

        <Card className="overflow-hidden shadow-card">
          <CardHeader>
            <CardTitle className="text-base">Aktivitas Terbaru</CardTitle>
          </CardHeader>
          <CardContent className="px-0 pb-0">
            <DataTable rows={aktivitasTerbaru} columns={aktivitasCols} rowKey={(r) => r.id} />
          </CardContent>
        </Card>
      </div>
    </>
  );
}
