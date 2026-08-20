import { useEffect, useState } from "react";
import { createFileRoute } from "@tanstack/react-router";
import { Loader2, Plus, Printer, Trash2 } from "lucide-react";
import { toast } from "sonner";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { PrintDialog, PrintRow } from "@/components/sigula/print-dialog";
import {
  DataTable,
  EmptyState,
  PageHeader,
  StatCard,
  type Column,
} from "@/components/sigula/ui-bits";
import { angka, rupiah, tanggalPendek } from "@/lib/format";
import { GRADES, type Grade } from "@/lib/sigula-types";
import { todayISO } from "@/lib/sigula-seed";
import { ApiError } from "@/lib/api-client";
import { getPembelian, type Pembelian, type PembelianKwitansi } from "@/lib/api/pembelian";
import { useBatalkanPembelian, usePembelianList, useTambahPembelian } from "@/hooks/use-pembelian";
import { useHargaBeli } from "@/hooks/use-master-data";
import { usePetaniList } from "@/hooks/use-petani";

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
      {
        property: "og:description",
        content: "Transaksi pembelian bahan mentah lengkap dengan kwitansi.",
      },
    ],
  }),
  component: PembelianPage,
});

const PER_PAGE = 25;

function apiErrorMessage(err: unknown, fallback: string): string {
  return err instanceof ApiError ? (err.firstFieldError ?? err.message) : fallback;
}

function LoadingRow() {
  return (
    <div className="flex items-center justify-center gap-2 px-4 py-14 text-sm text-muted-foreground">
      <Loader2 className="size-4 animate-spin" /> Memuat data…
    </div>
  );
}

/** Dropdown petani dengan pencarian nama/nomor member, dipakai di filter & form transaksi. */
function PetaniPicker({
  value,
  onChange,
  placeholder = "Pilih petani",
  allOptionLabel,
}: {
  value: string;
  onChange: (v: string) => void;
  placeholder?: string;
  /** Bila diisi, tampilkan opsi "semua" di atas daftar (dipakai untuk filter). */
  allOptionLabel?: string;
}) {
  const [q, setQ] = useState("");
  const [qDebounced, setQDebounced] = useState("");

  useEffect(() => {
    const t = setTimeout(() => setQDebounced(q.trim()), 250);
    return () => clearTimeout(t);
  }, [q]);

  const { data: petaniList = [] } = usePetaniList({ q: qDebounced || undefined });

  return (
    <div className="space-y-2">
      <Input
        placeholder="Cari nama / nomor member…"
        value={q}
        onChange={(e) => setQ(e.target.value)}
      />
      <Select value={value} onValueChange={onChange}>
        <SelectTrigger>
          <SelectValue placeholder={placeholder} />
        </SelectTrigger>
        <SelectContent>
          {allOptionLabel && <SelectItem value="semua">{allOptionLabel}</SelectItem>}
          {petaniList.map((p) => (
            <SelectItem key={p.id} value={p.id}>
              {p.nama}
              {p.labelMember ? ` — ${p.labelMember}` : " — Non-Member"}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  );
}

interface FormState {
  tanggal: string;
  petaniId: string;
  grade: Grade;
  kg: string;
  hargaManual: boolean;
  harga: string;
}

function PembelianPage() {
  // ---- Filter & pagination ---------------------------------------------------
  const [q, setQ] = useState("");
  const [qDebounced, setQDebounced] = useState("");
  const [dari, setDari] = useState("");
  const [sampai, setSampai] = useState("");
  const [fGrade, setFGrade] = useState<"semua" | Grade>("semua");
  const [fPetaniId, setFPetaniId] = useState("semua");
  const [page, setPage] = useState(1);

  useEffect(() => {
    const t = setTimeout(() => setQDebounced(q.trim()), 300);
    return () => clearTimeout(t);
  }, [q]);

  useEffect(() => {
    setPage(1);
  }, [qDebounced, dari, sampai, fGrade, fPetaniId]);

  const {
    data: listResult,
    isLoading,
    isError,
  } = usePembelianList({
    dari: dari || undefined,
    sampai: sampai || undefined,
    grade: fGrade === "semua" ? undefined : fGrade,
    petaniId: fPetaniId === "semua" ? undefined : fPetaniId,
    q: qDebounced || undefined,
    page,
    perPage: PER_PAGE,
  });

  const rows = listResult?.data ?? [];
  const meta = listResult?.meta;
  const ringkasan = listResult?.ringkasan;
  const filterAktif = Boolean(dari || sampai || fGrade !== "semua" || fPetaniId !== "semua" || q);

  const resetFilter = () => {
    setDari("");
    setSampai("");
    setFGrade("semua");
    setFPetaniId("semua");
    setQ("");
  };

  // ---- Harga master per grade (untuk prefill form) ---------------------------
  const { data: hargaData } = useHargaBeli();

  // ---- Transaksi baru ----------------------------------------------------------
  const tambahPembelian = useTambahPembelian();
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState<FormState>({
    tanggal: todayISO(),
    petaniId: "",
    grade: "NS 1",
    kg: "",
    hargaManual: false,
    harga: "",
  });
  const [err, setErr] = useState<string | null>(null);
  const [struk, setStruk] = useState<PembelianKwitansi | null>(null);

  const hargaMaster = hargaData?.hargaBeli[form.grade] ?? null;
  const hargaEfektif = form.hargaManual ? Number(form.harga) || 0 : (hargaMaster ?? 0);
  const totalPreview = (Number(form.kg) || 0) * hargaEfektif;

  const buka = () => {
    setForm({
      tanggal: todayISO(),
      petaniId: "",
      grade: "NS 1",
      kg: "",
      hargaManual: false,
      harga: "",
    });
    setErr(null);
    setOpen(true);
  };

  const simpan = async (cetak: boolean) => {
    if (!form.petaniId) return setErr("Petani wajib dipilih.");
    const kgNum = Number(form.kg);
    if (!form.kg.trim() || Number.isNaN(kgNum) || kgNum <= 0)
      return setErr("Kilogram harus lebih dari 0.");
    if (!form.tanggal) return setErr("Tanggal wajib diisi.");

    let hargaPayload: number | undefined;
    if (form.hargaManual) {
      const h = Number(form.harga);
      if (!form.harga.trim() || Number.isNaN(h) || h <= 0)
        return setErr("Harga manual harus lebih dari 0.");
      hargaPayload = h;
    } else if (hargaMaster === null) {
      return setErr(
        `Belum ada harga Master Harga untuk grade ${form.grade}. Isi harga manual, atau atur harganya dulu di menu Master.`,
      );
    }

    try {
      const res = await tambahPembelian.mutateAsync({
        tanggal: form.tanggal,
        petaniId: form.petaniId,
        grade: form.grade,
        kg: kgNum,
        harga: hargaPayload,
      });
      toast.success("Transaksi pembelian tersimpan", {
        description: `${res.kwitansi.namaPetani ?? "-"} — ${angka(kgNum)} kg ${form.grade}`,
      });
      setOpen(false);
      if (cetak) setStruk(res.kwitansi);
    } catch (e) {
      setErr(apiErrorMessage(e, "Gagal menyimpan transaksi pembelian."));
    }
  };

  // ---- Cetak ulang kwitansi ------------------------------------------------------
  const [printLoadingId, setPrintLoadingId] = useState<string | null>(null);

  const cetakUlang = async (id: string) => {
    setPrintLoadingId(id);
    try {
      const res = await getPembelian(id);
      setStruk(res.kwitansi);
    } catch (e) {
      toast.error(apiErrorMessage(e, "Gagal memuat data kwitansi."));
    } finally {
      setPrintLoadingId(null);
    }
  };

  // ---- Batalkan transaksi ------------------------------------------------------
  const batalkanPembelian = useBatalkanPembelian();
  const [batalTarget, setBatalTarget] = useState<Pembelian | null>(null);
  const [alasanBatal, setAlasanBatal] = useState("");

  const konfirmasiBatal = async () => {
    if (!batalTarget) return;
    try {
      const res = await batalkanPembelian.mutateAsync({
        id: batalTarget.id,
        alasan: alasanBatal.trim() || undefined,
      });
      toast.success(res.message, { description: batalTarget.nomorKwitansi });
      setBatalTarget(null);
      setAlasanBatal("");
    } catch (e) {
      toast.error(apiErrorMessage(e, "Gagal membatalkan transaksi."));
    }
  };

  const cols: Column<Pembelian>[] = [
    {
      key: "kwitansi",
      header: "No. Kwitansi",
      cell: (r) => <span className="font-mono text-xs">{r.nomorKwitansi}</span>,
    },
    { key: "tgl", header: "Tanggal", cell: (r) => tanggalPendek(r.tanggal) },
    {
      key: "petani",
      header: "Nama Petani",
      cell: (r) => <span className="font-medium">{r.namaPetani ?? "-"}</span>,
    },
    {
      key: "grade",
      header: "Grade",
      cell: (r) => <Badge variant="secondary">{r.grade}</Badge>,
    },
    { key: "kg", header: "Kilogram", align: "right", cell: (r) => `${angka(r.kg)} kg` },
    { key: "harga", header: "Harga/kg", align: "right", cell: (r) => rupiah(r.harga) },
    {
      key: "total",
      header: "Total Bayar",
      align: "right",
      cell: (r) => <span className="font-semibold">{rupiah(r.total)}</span>,
    },
    {
      key: "status",
      header: "Status",
      cell: (r) =>
        r.statusPembayaranKode === "lunas" ? (
          <Badge className="bg-success/15 text-success hover:bg-success/15">Lunas</Badge>
        ) : (
          <Badge variant="secondary">Belum Lunas</Badge>
        ),
    },
    {
      key: "aksi",
      header: "Aksi",
      align: "right",
      cell: (r) => (
        <div className="flex justify-end gap-1">
          <Button
            variant="ghost"
            size="icon"
            aria-label="Cetak kwitansi"
            disabled={printLoadingId === r.id}
            onClick={() => cetakUlang(r.id)}
          >
            {printLoadingId === r.id ? (
              <Loader2 className="size-4 animate-spin" />
            ) : (
              <Printer className="size-4" />
            )}
          </Button>
          <Button
            variant="ghost"
            size="icon"
            aria-label="Batalkan"
            onClick={() => {
              setBatalTarget(r);
              setAlasanBatal("");
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
        <StatCard
          label="Total Pembelian Hari Ini"
          value={ringkasan ? rupiah(ringkasan.hariIni) : "…"}
          tone="primary"
          hint={ringkasan ? `${angka(ringkasan.kgHariIni)} kg` : undefined}
        />
        <StatCard
          label="Total Pembelian Bulan Ini"
          value={ringkasan ? rupiah(ringkasan.bulanIni) : "…"}
          hint={ringkasan ? `${angka(ringkasan.kgBulanIni)} kg` : undefined}
        />
      </div>

      <Card className="mt-6 overflow-hidden shadow-card">
        <CardContent className="space-y-4 px-0 py-4">
          <div className="flex flex-wrap items-end gap-3 px-4">
            <div className="space-y-1">
              <Label className="text-xs">Cari (nama petani / no. kwitansi)</Label>
              <Input
                value={q}
                onChange={(e) => setQ(e.target.value)}
                placeholder="Cari..."
                className="w-[220px]"
              />
            </div>
            <div className="space-y-1">
              <Label className="text-xs">Dari Tanggal</Label>
              <Input
                type="date"
                value={dari}
                onChange={(e) => setDari(e.target.value)}
                className="w-[160px]"
              />
            </div>
            <div className="space-y-1">
              <Label className="text-xs">Sampai Tanggal</Label>
              <Input
                type="date"
                value={sampai}
                onChange={(e) => setSampai(e.target.value)}
                className="w-[160px]"
              />
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
            <div className="w-[220px] space-y-1">
              <Label className="text-xs">Petani</Label>
              <PetaniPicker
                value={fPetaniId}
                onChange={setFPetaniId}
                allOptionLabel="Semua Petani"
              />
            </div>
            {filterAktif && (
              <Button variant="ghost" onClick={resetFilter}>
                Reset filter
              </Button>
            )}
          </div>

          {isError && (
            <p className="px-4 text-sm text-destructive">
              Gagal memuat data pembelian. Coba muat ulang halaman.
            </p>
          )}

          {isLoading ? (
            <LoadingRow />
          ) : (
            <>
              <DataTable
                rows={rows}
                columns={cols}
                rowKey={(r) => r.id}
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
              {meta && meta.total > 0 && (
                <div className="flex flex-wrap items-center justify-between gap-3 px-4 text-sm text-muted-foreground">
                  <span>
                    Menampilkan {meta.from ?? 0}–{meta.to ?? 0} dari {angka(meta.total)} transaksi
                  </span>
                  <div className="flex items-center gap-2">
                    <Button
                      variant="outline"
                      size="sm"
                      disabled={meta.current_page <= 1}
                      onClick={() => setPage((p) => p - 1)}
                    >
                      Sebelumnya
                    </Button>
                    <span>
                      Halaman {meta.current_page} dari {meta.last_page}
                    </span>
                    <Button
                      variant="outline"
                      size="sm"
                      disabled={meta.current_page >= meta.last_page}
                      onClick={() => setPage((p) => p + 1)}
                    >
                      Berikutnya
                    </Button>
                  </div>
                </div>
              )}
            </>
          )}
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
              <PetaniPicker
                value={form.petaniId}
                onChange={(v) => setForm({ ...form, petaniId: v })}
                placeholder="Pilih petani"
              />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label>Grade</Label>
                <Select
                  value={form.grade}
                  onValueChange={(v: Grade) => setForm({ ...form, grade: v })}
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
            </div>

            <div className="space-y-2 rounded-xl border p-3">
              <div className="flex items-center justify-between gap-2">
                <div>
                  <p className="text-sm font-medium">Harga per kg</p>
                  <p className="text-xs text-muted-foreground">
                    {hargaMaster !== null
                      ? `Otomatis dari Master Harga: ${rupiah(hargaMaster)} / kg`
                      : `Belum ada harga Master Harga untuk grade ${form.grade}.`}
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  <Label htmlFor="harga-manual" className="cursor-pointer text-xs font-normal">
                    Isi manual
                  </Label>
                  <Switch
                    id="harga-manual"
                    checked={form.hargaManual}
                    onCheckedChange={(v) => setForm({ ...form, hargaManual: v })}
                  />
                </div>
              </div>
              {form.hargaManual && (
                <Input
                  type="number"
                  min={0}
                  value={form.harga}
                  onChange={(e) => setForm({ ...form, harga: e.target.value })}
                  placeholder="Harga negosiasi per kg"
                />
              )}
            </div>

            <div className="rounded-xl bg-cream px-4 py-3">
              <p className="text-xs uppercase tracking-wide text-muted-foreground">
                Estimasi Total Bayar
              </p>
              <p className="text-2xl font-semibold">{rupiah(totalPreview)}</p>
              <p className="text-xs text-muted-foreground">
                {angka(Number(form.kg) || 0)} kg × {rupiah(hargaEfektif)}
              </p>
            </div>
            {err && <p className="text-sm text-destructive">{err}</p>}
          </div>
          <DialogFooter className="flex-col gap-2 sm:flex-row">
            <Button variant="outline" onClick={() => setOpen(false)}>
              Batal
            </Button>
            <Button
              variant="secondary"
              onClick={() => simpan(false)}
              disabled={tambahPembelian.isPending}
            >
              {tambahPembelian.isPending && <Loader2 className="size-4 animate-spin" />}
              Simpan
            </Button>
            <Button onClick={() => simpan(true)} disabled={tambahPembelian.isPending}>
              {tambahPembelian.isPending ? (
                <Loader2 className="mr-2 size-4 animate-spin" />
              ) : (
                <Printer className="mr-2 size-4" />
              )}
              Simpan & Cetak Kwitansi
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={batalTarget !== null} onOpenChange={(v) => !v && setBatalTarget(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Batalkan Transaksi Pembelian</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <p className="text-sm text-muted-foreground">
              Transaksi{" "}
              <span className="font-medium text-foreground">{batalTarget?.nomorKwitansi}</span> akan
              dibatalkan dan stok bahan mentah yang sudah masuk akan dikembalikan. Tindakan ini
              tidak bisa dibatalkan.
            </p>
            <div className="space-y-2">
              <Label>Alasan pembatalan (opsional)</Label>
              <Textarea
                value={alasanBatal}
                onChange={(e) => setAlasanBatal(e.target.value)}
                placeholder="Contoh: Salah input kilogram"
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setBatalTarget(null)}>
              Tutup
            </Button>
            <Button
              variant="destructive"
              onClick={konfirmasiBatal}
              disabled={batalkanPembelian.isPending}
            >
              {batalkanPembelian.isPending && <Loader2 className="size-4 animate-spin" />}
              Ya, Batalkan
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <PrintDialog
        open={struk !== null}
        onOpenChange={(v) => !v && setStruk(null)}
        title="Kwitansi Pembelian"
      >
        {struk && (
          <div>
            <p className="mb-3 text-center text-sm font-semibold">KWITANSI PEMBELIAN BAHAN</p>
            <PrintRow label="No. Kwitansi" value={struk.nomor} />
            <PrintRow label="Tanggal" value={struk.tanggal} />
            <PrintRow label="Diterima dari" value="PT Nira Sari Murni" />
            <PrintRow label="Dibayarkan kepada" value={struk.namaPetani ?? "-"} />
            <PrintRow label="Nomor Member" value={struk.nomorMember} />
            <PrintRow label="Grade bahan" value={struk.grade} />
            <PrintRow label="Kilogram" value={`${angka(struk.kilogram)} kg`} />
            <PrintRow label="Harga per kg" value={rupiah(struk.hargaPerKg)} />
            <div className="mt-2 border-t pt-2">
              <PrintRow label="Total dibayar" value={rupiah(struk.total)} strong />
            </div>
            <PrintRow label="Status" value={struk.statusPembayaran.toUpperCase()} />
          </div>
        )}
      </PrintDialog>
    </>
  );
}
