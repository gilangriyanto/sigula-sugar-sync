import { useMemo, useState } from "react";
import { createFileRoute } from "@tanstack/react-router";
import { Plus, Printer, Trash2 } from "lucide-react";
import { toast } from "sonner";
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
import type { Penjualan } from "@/lib/sigula-types";
import { useSigula } from "@/store/sigula-store";

export const Route = createFileRoute("/_app/penjualan")({
  head: () => ({
    meta: [
      { title: "Penjualan ke Eksportir — SIGULA" },
      {
        name: "description",
        content:
          "Buat invoice penjualan gula kristal dan brondol dengan harga berbeda per baris, kalkulasi dua arah, validasi stok, dan cetak invoice.",
      },
      { property: "og:title", content: "Penjualan ke Eksportir — SIGULA" },
      { property: "og:description", content: "Invoice dua baris: gula kristal dan gula brondol." },
    ],
  }),
  component: PenjualanPage,
});

interface Baris {
  aktif: boolean;
  kg: string;
  harga: string;
  subtotal: string;
  autoHarga: boolean;
}

const barisAwal: Baris = { aktif: false, kg: "", harga: "", subtotal: "", autoHarga: false };

function PenjualanPage() {
  const { state, stok, addPenjualan, deletePenjualan, namaEksportir, today } = useSigula();
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState("");
  const [invoice, setInvoice] = useState<Penjualan | null>(null);
  const [eksportirId, setEksportirId] = useState("");
  const [tanggal, setTanggal] = useState(today);
  const [kristal, setKristal] = useState<Baris>(barisAwal);
  const [brondol, setBrondol] = useState<Baris>(barisAwal);
  const [err, setErr] = useState<string | null>(null);

  const num = (v: string) => Number(v) || 0;
  const subKristal = kristal.aktif ? num(kristal.kg) * num(kristal.harga) : 0;
  const subBrondol = brondol.aktif ? num(brondol.kg) * num(brondol.harga) : 0;
  const totalJual = subKristal + subBrondol;

  const setKgHarga = (
    which: "kristal" | "brondol",
    patch: Partial<Baris>,
  ) => {
    const setter = which === "kristal" ? setKristal : setBrondol;
    setter((prev) => {
      const next = { ...prev, ...patch, autoHarga: false };
      next.subtotal = String(num(next.kg) * num(next.harga));
      return next;
    });
  };

  /** kalkulasi dua arah: user edit subtotal -> harga menyesuaikan, kg tetap */
  const editSubtotal = (which: "kristal" | "brondol", value: string) => {
    const setter = which === "kristal" ? setKristal : setBrondol;
    setter((prev) => {
      if (num(prev.kg) <= 0) {
        setErr(`Kilogram ${which === "kristal" ? "kristal" : "brondol"} harus diisi sebelum mengubah subtotal.`);
        return { ...prev, subtotal: value };
      }
      setErr(null);
      const hargaBaru = num(value) / num(prev.kg);
      return { ...prev, subtotal: value, harga: String(Math.round(hargaBaru * 100) / 100), autoHarga: true };
    });
  };

  const reset = () => {
    setEksportirId("");
    setTanggal(today);
    setKristal(barisAwal);
    setBrondol(barisAwal);
    setErr(null);
  };

  const simpan = (cetak: boolean) => {
    if (!eksportirId) return setErr("Eksportir wajib dipilih.");
    if (!tanggal) return setErr("Tanggal wajib diisi.");
    if (!kristal.aktif && !brondol.aktif) return setErr("Minimal salah satu baris (kristal / brondol) harus diisi.");
    if (kristal.aktif) {
      if (num(kristal.kg) <= 0) return setErr("Kilogram kristal harus lebih dari 0.");
      if (num(kristal.harga) <= 0) return setErr("Harga jual kristal harus lebih dari 0.");
      if (num(kristal.kg) > stok["Kristal"]) return setErr(`Stok kristal hanya ${angka(stok["Kristal"])} kg.`);
    }
    if (brondol.aktif) {
      if (num(brondol.kg) <= 0) return setErr("Kilogram brondol harus lebih dari 0.");
      if (num(brondol.harga) <= 0) return setErr("Harga jual brondol harus lebih dari 0.");
      if (num(brondol.kg) > stok["Brondol"]) return setErr(`Stok brondol hanya ${angka(stok["Brondol"])} kg.`);
    }
    const trx = addPenjualan({
      tanggal,
      eksportirId,
      kristal: kristal.aktif ? { kg: num(kristal.kg), harga: num(kristal.harga) } : null,
      brondol: brondol.aktif ? { kg: num(brondol.kg), harga: num(brondol.harga) } : null,
    });
    toast.success("Transaksi penjualan tersimpan", {
      description: `${namaEksportir(eksportirId)} — ${rupiah(trx.total)}`,
    });
    setOpen(false);
    reset();
    if (cetak) setInvoice(trx);
  };

  const rows = useMemo(() => {
    const term = q.trim().toLowerCase();
    return term
      ? state.penjualan.filter(
          (p) =>
            namaEksportir(p.eksportirId).toLowerCase().includes(term) ||
            p.noInvoice.toLowerCase().includes(term),
        )
      : state.penjualan;
  }, [state.penjualan, q, namaEksportir]);

  const bulan = useMemo(() => {
    const mk = monthKey(today);
    const list = state.penjualan.filter((p) => monthKey(p.tanggal) === mk);
    return {
      rp: list.reduce((a, p) => a + p.total, 0),
      kgK: list.reduce((a, p) => a + (p.kristal?.kg ?? 0), 0),
      kgB: list.reduce((a, p) => a + (p.brondol?.kg ?? 0), 0),
    };
  }, [state.penjualan, today]);

  const cols: Column<Penjualan>[] = [
    { key: "tgl", header: "Tanggal", sortValue: (r) => r.tanggal, cell: (r) => tanggalPendek(r.tanggal) },
    { key: "inv", header: "No. Invoice", sortValue: (r) => r.noInvoice, cell: (r) => <span className="font-medium">{r.noInvoice}</span> },
    { key: "eks", header: "Eksportir", sortValue: (r) => namaEksportir(r.eksportirId), cell: (r) => namaEksportir(r.eksportirId) },
    {
      key: "k",
      header: "Kristal",
      align: "right",
      sortValue: (r) => r.kristal?.kg ?? 0,
      cell: (r) =>
        r.kristal ? (
          <div className="text-xs">
            <p className="font-medium">{angka(r.kristal.kg)} kg</p>
            <p className="text-muted-foreground">@ {rupiah(r.kristal.harga)}</p>
          </div>
        ) : (
          <span className="text-muted-foreground">—</span>
        ),
    },
    {
      key: "b",
      header: "Brondol",
      align: "right",
      sortValue: (r) => r.brondol?.kg ?? 0,
      cell: (r) =>
        r.brondol ? (
          <div className="text-xs">
            <p className="font-medium">{angka(r.brondol.kg)} kg</p>
            <p className="text-muted-foreground">@ {rupiah(r.brondol.harga)}</p>
          </div>
        ) : (
          <span className="text-muted-foreground">—</span>
        ),
    },
    { key: "total", header: "Total", align: "right", sortValue: (r) => r.total, cell: (r) => <span className="font-semibold">{rupiah(r.total)}</span> },
    {
      key: "aksi",
      header: "Aksi",
      align: "right",
      cell: (r) => (
        <div className="flex justify-end gap-1">
          <Button variant="ghost" size="icon" aria-label="Cetak invoice" onClick={() => setInvoice(r)}>
            <Printer className="size-4" />
          </Button>
          <Button
            variant="ghost"
            size="icon"
            aria-label="Hapus"
            onClick={() => {
              deletePenjualan(r.id);
              toast.success("Transaksi penjualan dihapus", { description: r.noInvoice });
            }}
          >
            <Trash2 className="size-4 text-destructive" />
          </Button>
        </div>
      ),
    },
  ];

  const barisForm = (
    label: string,
    baris: Baris,
    which: "kristal" | "brondol",
    stokTersedia: number,
  ) => (
    <div className="rounded-xl border p-4">
      <label className="flex items-center gap-2 text-sm font-medium">
        <input
          type="checkbox"
          checked={baris.aktif}
          onChange={(e) =>
            (which === "kristal" ? setKristal : setBrondol)({ ...baris, aktif: e.target.checked })
          }
          className="size-4 accent-[var(--primary)]"
        />
        Baris {label} (opsional)
      </label>
      <p className="mt-1 text-xs text-muted-foreground">
        Stok {label} Tersedia: <span className="font-medium">{angka(stokTersedia)} kg</span>
      </p>
      {baris.aktif && (
        <div className="mt-3 grid gap-3 sm:grid-cols-3">
          <div className="space-y-1">
            <Label className="text-xs">Kilogram</Label>
            <Input
              type="number"
              min={0}
              value={baris.kg}
              onChange={(e) => setKgHarga(which, { kg: e.target.value })}
              placeholder="0"
            />
          </div>
          <div className="space-y-1">
            <Label className="text-xs">Harga Jual / kg</Label>
            <Input
              type="number"
              min={0}
              value={baris.harga}
              onChange={(e) => setKgHarga(which, { harga: e.target.value })}
              className={baris.autoHarga ? "border-primary ring-2 ring-primary/25" : undefined}
              placeholder="0"
            />
            {baris.autoHarga && <p className="text-[11px] font-medium text-primary">Harga disesuaikan otomatis</p>}
          </div>
          <div className="space-y-1">
            <Label className="text-xs">Subtotal (bisa diedit)</Label>
            <Input
              type="number"
              min={0}
              value={baris.subtotal}
              onChange={(e) => editSubtotal(which, e.target.value)}
            />
          </div>
        </div>
      )}
    </div>
  );

  return (
    <>
      <PageHeader
        title="Penjualan ke Eksportir"
        subtitle="Satu invoice dapat memuat baris gula kristal dan gula brondol dengan harga berbeda"
        action={
          <Button
            onClick={() => {
              reset();
              setOpen(true);
            }}
          >
            <Plus className="mr-2 size-4" /> Transaksi Penjualan Baru
          </Button>
        }
      />

      <div className="grid gap-4 sm:grid-cols-3">
        <StatCard label="Total Penjualan Bulan Ini" value={rupiah(bulan.rp)} tone="primary" />
        <StatCard label="Kristal Terjual Bulan Ini" value={`${angka(bulan.kgK)} kg`} tone="success" />
        <StatCard label="Brondol Terjual Bulan Ini" value={`${angka(bulan.kgB)} kg`} tone="warning" />
      </div>

      <Card className="mt-6 overflow-hidden shadow-card">
        <CardContent className="space-y-4 px-0 py-4">
          <div className="px-4">
            <SearchInput value={q} onChange={setQ} placeholder="Cari eksportir / no. invoice..." />
          </div>
          <DataTable
            rows={rows}
            columns={cols}
            rowKey={(r) => r.id}
            initialSort={{ key: "tgl", dir: "desc" }}
            empty={<EmptyState title="Belum ada penjualan" description="Buat transaksi penjualan pertama." />}
          />
        </CardContent>
      </Card>

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
          <DialogHeader>
            <DialogTitle>Transaksi Penjualan Baru</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label>Eksportir</Label>
                <Select value={eksportirId} onValueChange={setEksportirId}>
                  <SelectTrigger>
                    <SelectValue placeholder="Pilih eksportir" />
                  </SelectTrigger>
                  <SelectContent>
                    {state.eksportir.map((e) => (
                      <SelectItem key={e.id} value={e.id}>
                        {e.nama}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>Tanggal</Label>
                <Input type="date" value={tanggal} onChange={(e) => setTanggal(e.target.value)} />
              </div>
            </div>

            {barisForm("Kristal", kristal, "kristal", stok["Kristal"])}
            {barisForm("Brondol", brondol, "brondol", stok["Brondol"])}

            <div className="rounded-xl bg-cream px-4 py-3">
              <div className="flex justify-between text-sm">
                <span className="text-muted-foreground">Subtotal Kristal</span>
                <span>{rupiah(subKristal)}</span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-muted-foreground">Subtotal Brondol</span>
                <span>{rupiah(subBrondol)}</span>
              </div>
              <div className="mt-2 flex justify-between border-t pt-2 text-lg font-semibold">
                <span>Total Penjualan</span>
                <span>{rupiah(totalJual)}</span>
              </div>
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
              <Printer className="mr-2 size-4" /> Simpan & Cetak Invoice
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <PrintDialog open={invoice !== null} onOpenChange={(v) => !v && setInvoice(null)} title="Invoice Penjualan">
        {invoice && (
          <div>
            <p className="mb-3 text-center text-sm font-semibold">INVOICE PENJUALAN</p>
            <PrintRow label="No. Invoice" value={invoice.noInvoice} />
            <PrintRow label="Tanggal" value={tanggalID(invoice.tanggal)} />
            <PrintRow label="Pembeli" value={namaEksportir(invoice.eksportirId)} />
            <div className="mt-2 border-t pt-2">
              {invoice.kristal && (
                <PrintRow
                  label={`Gula Kristal — ${angka(invoice.kristal.kg)} kg × ${rupiah(invoice.kristal.harga)}`}
                  value={rupiah(invoice.kristal.kg * invoice.kristal.harga)}
                />
              )}
              {invoice.brondol && (
                <PrintRow
                  label={`Gula Brondol — ${angka(invoice.brondol.kg)} kg × ${rupiah(invoice.brondol.harga)}`}
                  value={rupiah(invoice.brondol.kg * invoice.brondol.harga)}
                />
              )}
            </div>
            <div className="mt-2 border-t pt-2">
              <PrintRow label="Total" value={rupiah(invoice.total)} strong />
            </div>
          </div>
        )}
      </PrintDialog>
    </>
  );
}
