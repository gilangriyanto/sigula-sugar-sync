import { useMemo, useState } from "react";
import { createFileRoute } from "@tanstack/react-router";
import { ArrowDownCircle, ArrowUpCircle, ClipboardCheck } from "lucide-react";
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
import { angka, kg, tanggalPendek } from "@/lib/format";
import { GRADES, KATEGORI_STOK, type StokKategori, type StokMove } from "@/lib/sigula-types";
import { useSigula } from "@/store/sigula-store";

export const Route = createFileRoute("/_app/stok")({
  head: () => ({
    meta: [
      { title: "Manajemen Stok — SIGULA" },
      {
        name: "description",
        content:
          "Pantau stok bahan mentah per grade, stok gula kristal dan brondol, kartu stok keluar-masuk, serta lakukan stok opname.",
      },
      { property: "og:title", content: "Manajemen Stok — SIGULA" },
      { property: "og:description", content: "Kartu stok real-time bahan mentah dan produk jadi." },
    ],
  }),
  component: StokPage,
});

const AMBANG_MENIPIS = 1500;

function StokPage() {
  const { state, stok, addOpname, today } = useSigula();
  const [q, setQ] = useState("");
  const [fKategori, setFKategori] = useState<"semua" | StokKategori>("semua");
  const [fJenis, setFJenis] = useState<"semua" | "Masuk" | "Keluar">("semua");
  const [opnameOpen, setOpnameOpen] = useState(false);
  const [opname, setOpname] = useState({
    kategori: "NS 1" as StokKategori,
    fisik: "",
    alasan: "",
    tanggal: today,
  });
  const [err, setErr] = useState<string | null>(null);

  const rows = useMemo(() => {
    const term = q.trim().toLowerCase();
    return state.stokMoves
      .filter((m) => (fKategori === "semua" ? true : m.kategori === fKategori))
      .filter((m) => (fJenis === "semua" ? true : m.jenis === fJenis))
      .filter((m) => (term ? m.keterangan.toLowerCase().includes(term) : true))
      .slice(0, 300);
  }, [state.stokMoves, q, fKategori, fJenis]);

  const selisih = (Number(opname.fisik) || 0) - stok[opname.kategori];

  const simpanOpname = () => {
    if (!opname.fisik.trim() || Number.isNaN(Number(opname.fisik)))
      return setErr("Jumlah stok fisik wajib diisi berupa angka.");
    if (Number(opname.fisik) < 0) return setErr("Jumlah stok fisik tidak boleh negatif.");
    if (!opname.alasan.trim()) return setErr("Alasan koreksi wajib diisi.");
    if (selisih === 0) return setErr("Tidak ada selisih dengan stok sistem.");
    addOpname(opname.kategori, selisih, opname.alasan.trim(), opname.tanggal);
    toast.success("Stok opname tercatat", {
      description: `${opname.kategori}: koreksi ${selisih > 0 ? "+" : "−"}${angka(Math.abs(selisih))} kg`,
    });
    setOpnameOpen(false);
    setOpname({ kategori: "NS 1", fisik: "", alasan: "", tanggal: today });
    setErr(null);
  };

  const cols: Column<StokMove>[] = [
    { key: "tgl", header: "Tanggal", sortValue: (r) => r.tanggal, cell: (r) => tanggalPendek(r.tanggal) },
    {
      key: "jenis",
      header: "Jenis",
      sortValue: (r) => r.jenis,
      cell: (r) =>
        r.jenis === "Masuk" ? (
          <Badge className="bg-success/15 text-success hover:bg-success/15">
            <ArrowDownCircle className="mr-1 size-3" /> Masuk
          </Badge>
        ) : (
          <Badge variant="secondary">
            <ArrowUpCircle className="mr-1 size-3" /> Keluar
          </Badge>
        ),
    },
    {
      key: "kat",
      header: "Kategori",
      sortValue: (r) => r.kategori,
      cell: (r) => (
        <span>
          {GRADES.includes(r.kategori as never) ? `Bahan Mentah ${r.kategori}` : `Produk ${r.kategori}`}
        </span>
      ),
    },
    {
      key: "jml",
      header: "Jumlah",
      align: "right",
      sortValue: (r) => r.jumlah,
      cell: (r) => (
        <span className={r.jenis === "Masuk" ? "font-medium text-success" : "font-medium"}>
          {r.jenis === "Masuk" ? "+" : "−"}
          {angka(r.jumlah)} kg
        </span>
      ),
    },
    { key: "ket", header: "Keterangan", cell: (r) => <span className="text-muted-foreground">{r.keterangan}</span> },
  ];

  return (
    <>
      <PageHeader
        title="Manajemen Stok"
        subtitle="Posisi stok dan kartu stok keluar-masuk"
        action={
          <Button onClick={() => setOpnameOpen(true)} variant="outline">
            <ClipboardCheck className="mr-2 size-4" /> Stok Opname
          </Button>
        }
      />

      <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
        Stok Bahan Mentah
      </h2>
      <div className="grid gap-4 md:grid-cols-3">
        {GRADES.map((g) => {
          const aman = stok[g] >= AMBANG_MENIPIS;
          return (
            <Card key={g} className="shadow-card">
              <CardHeader className="pb-2">
                <CardTitle className="flex items-center justify-between text-base">
                  <span>Grade {g}</span>
                  {aman ? (
                    <Badge className="bg-success/15 text-success hover:bg-success/15">Aman</Badge>
                  ) : (
                    <Badge className="bg-warning/25 text-warning-foreground hover:bg-warning/25">Menipis</Badge>
                  )}
                </CardTitle>
              </CardHeader>
              <CardContent>
                <p className="text-3xl font-semibold tracking-tight">{kg(stok[g])}</p>
                <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-secondary">
                  <div
                    className={aman ? "h-full bg-success" : "h-full bg-warning"}
                    style={{ width: `${Math.min(100, (stok[g] / (AMBANG_MENIPIS * 3)) * 100)}%` }}
                  />
                </div>
                <p className="mt-2 text-xs text-muted-foreground">Ambang menipis: {kg(AMBANG_MENIPIS)}</p>
              </CardContent>
            </Card>
          );
        })}
      </div>

      <h2 className="mb-3 mt-8 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
        Stok Produk Jadi
      </h2>
      <div className="grid gap-4 md:grid-cols-2">
        <StatCard label="Gula Kristal" value={kg(stok["Kristal"])} tone="success" hint="Produk utama" />
        <StatCard label="Gula Brondol" value={kg(stok["Brondol"])} tone="warning" hint="Hasil sampingan" />
      </div>

      <Card className="mt-8 overflow-hidden shadow-card">
        <CardHeader>
          <CardTitle className="text-base">Kartu Stok</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4 px-0">
          <div className="flex flex-wrap items-end gap-3 px-4">
            <SearchInput value={q} onChange={setQ} placeholder="Cari keterangan..." />
            <div className="space-y-1">
              <Label className="text-xs">Kategori</Label>
              <Select value={fKategori} onValueChange={(v: "semua" | StokKategori) => setFKategori(v)}>
                <SelectTrigger className="w-[180px]">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="semua">Semua Kategori</SelectItem>
                  {KATEGORI_STOK.map((k) => (
                    <SelectItem key={k} value={k}>
                      {k}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1">
              <Label className="text-xs">Jenis</Label>
              <Select value={fJenis} onValueChange={(v: "semua" | "Masuk" | "Keluar") => setFJenis(v)}>
                <SelectTrigger className="w-[140px]">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="semua">Semua</SelectItem>
                  <SelectItem value="Masuk">Masuk</SelectItem>
                  <SelectItem value="Keluar">Keluar</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
          <DataTable
            rows={rows}
            columns={cols}
            rowKey={(r) => r.id}
            initialSort={{ key: "tgl", dir: "desc" }}
            empty={
              <EmptyState
                title="Kartu stok kosong"
                description="Belum ada pergerakan stok yang cocok dengan filter."
              />
            }
          />
        </CardContent>
      </Card>

      <Dialog open={opnameOpen} onOpenChange={setOpnameOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Stok Opname</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-2">
              <Label>Kategori Stok</Label>
              <Select
                value={opname.kategori}
                onValueChange={(v: StokKategori) => setOpname({ ...opname, kategori: v })}
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {KATEGORI_STOK.map((k) => (
                    <SelectItem key={k} value={k}>
                      {k}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <p className="text-xs text-muted-foreground">
                Stok sistem saat ini: {kg(stok[opname.kategori])}
              </p>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label>Stok Fisik Hasil Hitung (kg)</Label>
                <Input
                  type="number"
                  min={0}
                  value={opname.fisik}
                  onChange={(e) => setOpname({ ...opname, fisik: e.target.value })}
                />
              </div>
              <div className="space-y-2">
                <Label>Tanggal</Label>
                <Input
                  type="date"
                  value={opname.tanggal}
                  onChange={(e) => setOpname({ ...opname, tanggal: e.target.value })}
                />
              </div>
            </div>
            <div className="space-y-2">
              <Label>Alasan Koreksi</Label>
              <Input
                value={opname.alasan}
                onChange={(e) => setOpname({ ...opname, alasan: e.target.value })}
                placeholder="Contoh: susut penyimpanan"
              />
            </div>
            <div className="rounded-xl bg-cream px-4 py-3 text-sm">
              Selisih koreksi:{" "}
              <span className="font-semibold">
                {selisih > 0 ? "+" : selisih < 0 ? "−" : ""}
                {angka(Math.abs(selisih))} kg
              </span>
            </div>
            {err && <p className="text-sm text-destructive">{err}</p>}
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setOpnameOpen(false)}>
              Batal
            </Button>
            <Button onClick={simpanOpname}>Simpan Koreksi</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
