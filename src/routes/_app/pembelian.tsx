import { useMemo, useState } from "react";
import { createFileRoute } from "@tanstack/react-router";
import { Plus, Printer, Trash2 } from "lucide-react";
import { toast } from "sonner";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { PrintDialog, PrintRow } from "@/components/sigula/print-dialog";
import {
  DataTable,
  EmptyState,
  PageHeader,
  SearchInput,
  StatCard,
  type Column,
} from "@/components/sigula/ui-bits";
import { angka, monthKey, rupiah, tanggalID, tanggalPendek } from "@/lib/format";
import { GRADES, type Grade, type Pembelian } from "@/lib/sigula-types";
import { useSigula } from "@/store/sigula-store";

export const Route = createFileRoute("/_app/pembelian")({
  head: () => ({
    meta: [
      { title: "Pembelian Bahan dari Petani — SIGULA" },
      {
        name: "description",
        content:
          "Catat pembelian nira/bahan mentah dari petani per grade, hitung total bayar otomatis, dan cetak kwitansi pembelian.",
      },
      { property: "og:title", content: "Pembelian Bahan dari Petani — SIGULA" },
      { property: "og:description", content: "Transaksi pembelian bahan mentah lengkap dengan kwitansi." },
    ],
  }),
  component: PembelianPage,
});

function PembelianPage() {
  const { state, addPembelian, deletePembelian, namaPetani, today } = useSigula();
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState("");
  const [fTanggal, setFTanggal] = useState("");
  const [fGrade, setFGrade] = useState<"semua" | Grade>("semua");
  const [struk, setStruk] = useState<Pembelian | null>(null);

  const [form, setForm] = useState({
    tanggal: today,
    petaniId: "",
    grade: "NS 1" as Grade,
    kg: "",
    harga: String(state.hargaBeli["NS 1"]),
  });
  const [petaniQ, setPetaniQ] = useState("");
  const [err, setErr] = useState<string | null>(null);

  const total = (Number(form.kg) || 0) * (Number(form.harga) || 0);

  const petaniOptions = useMemo(() => {
    const term = petaniQ.trim().toLowerCase();
    const list = term
      ? state.petani.filter((p) => p.nama.toLowerCase().includes(term) || p.nomorMember.includes(term))
      : state.petani;
    return list.slice(0, 40);
  }, [state.petani, petaniQ]);

  const rows = useMemo(() => {
    const term = q.trim().toLowerCase();
    return state.pembelian
      .filter((p) => (fTanggal ? p.tanggal === fTanggal : true))
      .filter((p) => (fGrade === "semua" ? true : p.grade === fGrade))
      .filter((p) => (term ? namaPetani(p.petaniId).toLowerCase().includes(term) : true));
  }, [state.pembelian, q, fTanggal, fGrade, namaPetani]);

  const totalHariIni = state.pembelian.filter((p) => p.tanggal === today).reduce((a, p) => a + p.total, 0);
  const totalBulanIni = state.pembelian
    .filter((p) => monthKey(p.tanggal) === monthKey(today))
    .reduce((a, p) => a + p.total, 0);

  const buka = () => {
    setForm({ tanggal: today, petaniId: "", grade: "NS 1", kg: "", harga: String(state.hargaBeli["NS 1"]) });
    setPetaniQ("");
    setErr(null);
    setOpen(true);
  };

  const simpan = (cetak: boolean) => {
    if (!form.petaniId) return setErr("Petani wajib dipilih.");
    const kgNum = Number(form.kg);
    const hargaNum = Number(form.harga);
    if (!form.kg.trim() || Number.isNaN(kgNum) || kgNum <= 0) return setErr("Kilogram harus lebih dari 0.");
    if (Number.isNaN(hargaNum) || hargaNum <= 0) return setErr("Harga per kg harus lebih dari 0.");
    if (!form.tanggal) return setErr("Tanggal wajib diisi.");
    const trx = addPembelian({
      tanggal: form.tanggal,
      petaniId: form.petaniId,
      grade: form.grade,
      kg: kgNum,
      harga: hargaNum,
    });
    toast.success("Transaksi pembelian tersimpan", {
      description: `${namaPetani(form.petaniId)} — ${angka(kgNum)} kg ${form.grade}`,
    });
    setOpen(false);
    if (cetak) setStruk(trx);
  };

  const cols: Column<Pembelian>[] = [
    { key: "tgl", header: "Tanggal", sortValue: (r) => r.tanggal, cell: (r) => tanggalPendek(r.tanggal) },
    {
      key: "petani",
      header: "Nama Petani",
      sortValue: (r) => namaPetani(r.petaniId),
      cell: (r) => <span className="font-medium">{namaPetani(r.petaniId)}</span>,
    },
    { key: "grade", header: "Grade", sortValue: (r) => r.grade, cell: (r) => <Badge variant="secondary">{r.grade}</Badge> },
    { key: "kg", header: "Kilogram", align: "right", sortValue: (r) => r.kg, cell: (r) => `${angka(r.kg)} kg` },
    { key: "harga", header: "Harga/kg", align: "right", sortValue: (r) => r.harga, cell: (r) => rupiah(r.harga) },
    {
      key: "total",
      header: "Total Bayar",
      align: "right",
      sortValue: (r) => r.total,
      cell: (r) => <span className="font-semibold">{rupiah(r.total)}</span>,
    },
    {
      key: "status",
      header: "Status",
      cell: () => <Badge className="bg-success/15 text-success hover:bg-success/15">Lunas</Badge>,
    },
    {
      key: "aksi",
      header: "Aksi",
      align: "right",
      cell: (r) => (
        <div className="flex justify-end gap-1">
          <Button variant="ghost" size="icon" aria-label="Cetak kwitansi" onClick={() => setStruk(r)}>
            <Printer className="size-4" />
          </Button>
          <Button
            variant="ghost"
            size="icon"
            aria-label="Hapus"
            onClick={() => {
              deletePembelian(r.id);
              toast.success("Transaksi pembelian dihapus");
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
        title="Pembelian Bahan dari Petani"
        subtitle="Pencatatan pembelian bahan mentah per grade"
        action={
          <Button onClick={buka}>
            <Plus className="mr-2 size-4" /> Transaksi Baru
          </Button>
        }
      />

      <div className="grid gap-4 sm:grid-cols-2">
        <StatCard label="Total Pembelian Hari Ini" value={rupiah(totalHariIni)} tone="primary" />
        <StatCard label="Total Pembelian Bulan Ini" value={rupiah(totalBulanIni)} tone="default" />
      </div>

      <Card className="mt-6 overflow-hidden shadow-card">
        <CardContent className="space-y-4 px-0 py-4">
          <div className="flex flex-wrap items-end gap-3 px-4">
            <SearchInput value={q} onChange={setQ} placeholder="Cari nama petani..." />
            <div className="space-y-1">
              <Label className="text-xs">Tanggal</Label>
              <Input type="date" value={fTanggal} onChange={(e) => setFTanggal(e.target.value)} className="w-[170px]" />
            </div>
            <div className="space-y-1">
              <Label className="text-xs">Grade</Label>
              <Select value={fGrade} onValueChange={(v: "semua" | Grade) => setFGrade(v)}>
                <SelectTrigger className="w-[140px]">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="semua">Semua Grade</SelectItem>
                  {GRADES.map((g) => (
                    <SelectItem key={g} value={g}>
                      {g}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            {(fTanggal || fGrade !== "semua" || q) && (
              <Button
                variant="ghost"
                onClick={() => {
                  setFTanggal("");
                  setFGrade("semua");
                  setQ("");
                }}
              >
                Reset filter
              </Button>
            )}
          </div>
          <DataTable
            rows={rows}
            columns={cols}
            rowKey={(r) => r.id}
            initialSort={{ key: "tgl", dir: "desc" }}
            empty={
              <EmptyState
                title="Belum ada transaksi"
                description="Tidak ada pembelian yang cocok dengan filter saat ini."
                action={
                  <Button onClick={buka}>
                    <Plus className="mr-2 size-4" /> Transaksi Baru
                  </Button>
                }
              />
            }
          />
        </CardContent>
      </Card>

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle>Transaksi Pembelian Baru</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-2">
              <Label>Tanggal</Label>
              <Input
                type="date"
                value={form.tanggal}
                onChange={(e) => setForm({ ...form, tanggal: e.target.value })}
              />
            </div>
            <div className="space-y-2">
              <Label>Petani</Label>
              <Input
                placeholder="Cari petani (nama / nomor member)..."
                value={petaniQ}
                onChange={(e) => setPetaniQ(e.target.value)}
              />
              <Select value={form.petaniId} onValueChange={(v) => setForm({ ...form, petaniId: v })}>
                <SelectTrigger>
                  <SelectValue placeholder="Pilih petani" />
                </SelectTrigger>
                <SelectContent>
                  {petaniOptions.map((p) => (
                    <SelectItem key={p.id} value={p.id}>
                      {p.nama}
                      {p.nomorMember ? ` — Petani ${p.nomorMember}` : " — Non-Member"}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="grid gap-4 sm:grid-cols-3">
              <div className="space-y-2">
                <Label>Grade</Label>
                <Select
                  value={form.grade}
                  onValueChange={(v: Grade) =>
                    setForm({ ...form, grade: v, harga: String(state.hargaBeli[v]) })
                  }
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {GRADES.map((g) => (
                      <SelectItem key={g} value={g}>
                        {g}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>Kilogram</Label>
                <Input
                  type="number"
                  min={0}
                  value={form.kg}
                  onChange={(e) => setForm({ ...form, kg: e.target.value })}
                  placeholder="0"
                />
              </div>
              <div className="space-y-2">
                <Label>Harga / kg</Label>
                <Input
                  type="number"
                  min={0}
                  value={form.harga}
                  onChange={(e) => setForm({ ...form, harga: e.target.value })}
                />
              </div>
            </div>
            <div className="rounded-xl bg-cream px-4 py-3">
              <p className="text-xs uppercase tracking-wide text-muted-foreground">Total Bayar</p>
              <p className="text-2xl font-semibold">{rupiah(total)}</p>
              <p className="text-xs text-muted-foreground">
                {angka(Number(form.kg) || 0)} kg × {rupiah(Number(form.harga) || 0)}
              </p>
            </div>
            {err && <p className="text-sm text-destructive">{err}</p>}
          </div>
          <DialogFooter className="flex-col gap-2 sm:flex-row">
            <Button variant="outline" onClick={() => setOpen(false)}>
              Batal
            </Button>
            <Button variant="secondary" onClick={() => simpan(false)}>
              Simpan
            </Button>
            <Button onClick={() => simpan(true)}>
              <Printer className="mr-2 size-4" /> Simpan & Cetak Kwitansi
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <PrintDialog open={struk !== null} onOpenChange={(v) => !v && setStruk(null)} title="Kwitansi Pembelian">
        {struk && (
          <div>
            <p className="mb-3 text-center text-sm font-semibold">KWITANSI PEMBELIAN BAHAN</p>
            <PrintRow label="Tanggal" value={tanggalID(struk.tanggal)} />
            <PrintRow label="Diterima dari" value="PT Nira Sari Murni" />
            <PrintRow label="Dibayarkan kepada" value={namaPetani(struk.petaniId)} />
            <PrintRow label="Grade bahan" value={struk.grade} />
            <PrintRow label="Kilogram" value={`${angka(struk.kg)} kg`} />
            <PrintRow label="Harga per kg" value={rupiah(struk.harga)} />
            <div className="mt-2 border-t pt-2">
              <PrintRow label="Total dibayar" value={rupiah(struk.total)} strong />
            </div>
            <PrintRow label="Status" value="LUNAS" />
          </div>
        )}
      </PrintDialog>
    </>
  );
}
