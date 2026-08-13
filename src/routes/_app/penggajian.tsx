import { useMemo, useState } from "react";
import { createFileRoute } from "@tanstack/react-router";
import { ChevronLeft, ChevronRight, Printer, Wallet } from "lucide-react";
import { toast } from "sonner";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { PrintDialog, PrintRow } from "@/components/sigula/print-dialog";
import { DataTable, EmptyState, PageHeader, SearchInput, StatCard, type Column } from "@/components/sigula/ui-bits";
import { addDays, angka, mondayOf, rupiah, tanggalID } from "@/lib/format";
import type { BarisGaji } from "@/lib/sigula-types";
import { useSigula } from "@/store/sigula-store";

export const Route = createFileRoute("/_app/penggajian")({
  head: () => ({
    meta: [
      { title: "Penggajian Karyawan — SIGULA" },
      {
        name: "description",
        content:
          "Hitung gaji karyawan periode Senin-Jumat: upah per kg gula kristal & brondol, uang makan harian, dan cetak slip gaji lengkap.",
      },
      { property: "og:title", content: "Penggajian Karyawan — SIGULA" },
      { property: "og:description", content: "Penggajian mingguan berbasis hasil sesi tungku." },
    ],
  }),
  component: PenggajianPage,
});

function PenggajianPage() {
  const { state, today, payrollMinggu, bayarGaji, bayarSemuaGaji } = useSigula();
  const [senin, setSenin] = useState(() => mondayOf(today));
  const [q, setQ] = useState("");
  const [slip, setSlip] = useState<BarisGaji | null>(null);

  const jumat = addDays(senin, 4);
  const rows = payrollMinggu(senin);
  const filtered = useMemo(() => {
    const term = q.trim().toLowerCase();
    return term ? rows.filter((r) => r.nama.toLowerCase().includes(term)) : rows;
  }, [rows, q]);

  const totalMinggu = rows.reduce((a, r) => a + r.total, 0);
  const belum = rows.filter((r) => !r.dibayar).reduce((a, r) => a + r.total, 0);

  const cols: Column<BarisGaji>[] = [
    { key: "nama", header: "Nama", sortValue: (r) => r.nama, cell: (r) => <span className="font-medium">{r.nama}</span> },
    { key: "kristal", header: "Kg Kristal", align: "right", sortValue: (r) => r.kgKristal, cell: (r) => `${angka(r.kgKristal, 1)} kg` },
    { key: "brondol", header: "Kg Brondol", align: "right", sortValue: (r) => r.kgBrondol, cell: (r) => `${angka(r.kgBrondol, 1)} kg` },
    { key: "hari", header: "Hari Kerja", align: "center", sortValue: (r) => r.hariKerja, cell: (r) => `${r.hariKerja} hari` },
    { key: "uk", header: "Upah Kristal", align: "right", sortValue: (r) => r.upahKristal, cell: (r) => rupiah(r.upahKristal) },
    { key: "ub", header: "Upah Brondol", align: "right", sortValue: (r) => r.upahBrondol, cell: (r) => rupiah(r.upahBrondol) },
    { key: "um", header: "Uang Makan", align: "right", sortValue: (r) => r.uangMakan, cell: (r) => rupiah(r.uangMakan) },
    {
      key: "total",
      header: "Total Gaji",
      align: "right",
      sortValue: (r) => r.total,
      cell: (r) => <span className="font-semibold">{rupiah(r.total)}</span>,
    },
    {
      key: "status",
      header: "Status",
      sortValue: (r) => String(r.dibayar),
      cell: (r) =>
        r.dibayar ? (
          <Badge className="bg-success/15 text-success hover:bg-success/15">Sudah Dibayar</Badge>
        ) : (
          <Badge className="bg-warning/25 text-warning-foreground hover:bg-warning/25">Belum Dibayar</Badge>
        ),
    },
    {
      key: "aksi",
      header: "Aksi",
      align: "right",
      cell: (r) => (
        <div className="flex justify-end gap-1">
          {!r.dibayar && (
            <Button
              size="sm"
              onClick={() => {
                bayarGaji(r.karyawanId, senin);
                toast.success("Gaji dibayarkan", { description: `${r.nama} — ${rupiah(r.total)}` });
              }}
            >
              Bayar
            </Button>
          )}
          <Button variant="ghost" size="icon" aria-label="Cetak slip gaji" onClick={() => setSlip(r)}>
            <Printer className="size-4" />
          </Button>
        </div>
      ),
    },
  ];

  return (
    <>
      <PageHeader
        title="Penggajian Karyawan"
        subtitle="Periode Senin s.d. Jumat, dibayarkan setiap hari Jumat"
        action={
          <Button
            onClick={() => {
              if (!rows.length) {
                toast.error("Tidak ada data gaji pada periode ini");
                return;
              }
              bayarSemuaGaji(senin);
              toast.success("Semua gaji minggu ini ditandai sudah dibayar");
            }}
          >
            <Wallet className="mr-2 size-4" /> Bayar Semua
          </Button>
        }
      />

      <Card className="mb-4 shadow-card">
        <CardContent className="flex flex-wrap items-center justify-between gap-3 p-4">
          <div className="flex items-center gap-2">
            <Button variant="outline" size="icon" onClick={() => setSenin(addDays(senin, -7))} aria-label="Minggu sebelumnya">
              <ChevronLeft className="size-4" />
            </Button>
            <Button variant="outline" onClick={() => setSenin(mondayOf(today))}>
              Minggu Ini
            </Button>
            <Button variant="outline" size="icon" onClick={() => setSenin(addDays(senin, 7))} aria-label="Minggu berikutnya">
              <ChevronRight className="size-4" />
            </Button>
          </div>
          <p className="text-sm font-medium">
            Periode: {tanggalID(senin)} — {tanggalID(jumat)}
          </p>
        </CardContent>
      </Card>

      <div className="grid gap-4 sm:grid-cols-3">
        <StatCard label="Total Gaji Minggu Ini" value={rupiah(totalMinggu)} tone="primary" hint={`Dibayarkan ${tanggalID(jumat)}`} />
        <StatCard label="Belum Dibayar" value={rupiah(belum)} tone="warning" />
        <StatCard label="Karyawan Aktif Periode Ini" value={`${rows.length} orang`} />
      </div>

      <Card className="mt-6 overflow-hidden shadow-card">
        <CardContent className="space-y-4 px-0 py-4">
          <div className="px-4">
            <SearchInput value={q} onChange={setQ} placeholder="Cari nama karyawan..." />
          </div>
          <DataTable
            rows={filtered}
            columns={cols}
            rowKey={(r) => r.karyawanId}
            initialSort={{ key: "total", dir: "desc" }}
            empty={
              <EmptyState
                title="Belum ada data gaji"
                description="Tidak ada sesi tungku selesai pada periode Senin-Jumat ini."
              />
            }
          />
        </CardContent>
      </Card>

      <PrintDialog open={slip !== null} onOpenChange={(v) => !v && setSlip(null)} title="Slip Gaji Karyawan">
        {slip && (
          <div>
            <p className="mb-3 text-center text-sm font-semibold">SLIP GAJI MINGGUAN</p>
            <PrintRow label="Nama karyawan" value={slip.nama} />
            <PrintRow label="Periode" value={`${tanggalID(senin)} — ${tanggalID(jumat)}`} />
            <PrintRow label="Hari kerja" value={`${slip.hariKerja} hari`} />
            <div className="mt-2 border-t pt-2">
              <PrintRow label={`Kg kristal × ${rupiah(state.tarif.kristal)}`} value={`${angka(slip.kgKristal, 1)} kg`} />
              <PrintRow label="Upah kristal" value={rupiah(slip.upahKristal)} />
              <PrintRow label={`Kg brondol × ${rupiah(state.tarif.brondol)}`} value={`${angka(slip.kgBrondol, 1)} kg`} />
              <PrintRow label="Upah brondol" value={rupiah(slip.upahBrondol)} />
              <PrintRow label={`Uang makan (${slip.hariKerja} × ${rupiah(state.tarif.uangMakan)})`} value={rupiah(slip.uangMakan)} />
            </div>
            <div className="mt-2 border-t pt-2">
              <PrintRow label="Total gaji" value={rupiah(slip.total)} strong />
              <PrintRow label="Status" value={slip.dibayar ? "SUDAH DIBAYAR" : "BELUM DIBAYAR"} />
            </div>
          </div>
        )}
      </PrintDialog>
    </>
  );
}
