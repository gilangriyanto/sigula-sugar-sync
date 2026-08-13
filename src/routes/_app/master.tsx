import { useState } from "react";
import { createFileRoute } from "@tanstack/react-router";
import { Plus, Trash2, Wallet } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { DataTable, EmptyState, PageHeader, type Column } from "@/components/sigula/ui-bits";
import { rupiah, tanggalID } from "@/lib/format";
import { GRADES, type Eksportir, type Grade, type Karyawan, type RiwayatHarga } from "@/lib/sigula-types";
import { useSigula } from "@/store/sigula-store";

export const Route = createFileRoute("/_app/master")({
  head: () => ({
    meta: [
      { title: "Master Harga & Tarif — SIGULA" },
      {
        name: "description",
        content:
          "Atur harga beli bahan per grade (NS 1, NS 2, Kecap), tarif gaji per kg gula kristal dan brondol, uang makan harian, serta data karyawan dan eksportir.",
      },
      { property: "og:title", content: "Master Harga & Tarif — SIGULA" },
      { property: "og:description", content: "Pusat pengaturan harga beli, tarif produksi, dan data mitra." },
    ],
  }),
  component: MasterPage,
});

function MasterPage() {
  const {
    state,
    setHarga,
    setTarif,
    addKaryawan,
    deleteKaryawan,
    addEksportir,
    deleteEksportir,
    today,
  } = useSigula();

  const [hargaDialog, setHargaDialog] = useState<Grade | null>(null);
  const [hargaBaru, setHargaBaru] = useState("");
  const [tanggalBerlaku, setTanggalBerlaku] = useState(today);
  const [errHarga, setErrHarga] = useState<string | null>(null);

  const [tarifDialog, setTarifDialog] = useState<null | "kristal" | "brondol" | "uangMakan">(null);
  const [tarifValue, setTarifValue] = useState("");

  const [kForm, setKForm] = useState({ nama: "", kontak: "" });
  const [eForm, setEForm] = useState({ nama: "", kontak: "" });

  const bukaHarga = (g: Grade) => {
    setHargaDialog(g);
    setHargaBaru(String(state.hargaBeli[g]));
    setTanggalBerlaku(today);
    setErrHarga(null);
  };

  const simpanHarga = () => {
    const n = Number(hargaBaru);
    if (!hargaBaru.trim() || Number.isNaN(n)) return setErrHarga("Harga wajib diisi berupa angka.");
    if (n <= 0) return setErrHarga("Harga harus lebih besar dari 0.");
    if (!tanggalBerlaku) return setErrHarga("Tanggal berlaku wajib diisi.");
    setHarga(hargaDialog!, n, tanggalBerlaku);
    toast.success(`Harga ${hargaDialog} diperbarui`, { description: `${rupiah(n)} / kg` });
    setHargaDialog(null);
  };

  const bukaTarif = (key: "kristal" | "brondol" | "uangMakan") => {
    setTarifDialog(key);
    setTarifValue(String(state.tarif[key]));
  };

  const simpanTarif = () => {
    const n = Number(tarifValue);
    if (Number.isNaN(n) || n < 0) {
      toast.error("Nilai tarif tidak valid");
      return;
    }
    setTarif({ [tarifDialog!]: n });
    toast.success("Tarif berhasil diperbarui", { description: rupiah(n) });
    setTarifDialog(null);
  };

  const riwayatCols = (g: Grade): Column<RiwayatHarga>[] => [
    { key: "tgl", header: "Tanggal", sortValue: (r) => r.tanggal, cell: (r) => tanggalID(r.tanggal) },
    { key: "lama", header: "Harga Lama", align: "right", cell: (r) => rupiah(r.hargaLama) },
    {
      key: "baru",
      header: "Harga Baru",
      align: "right",
      cell: (r) => <span className="font-medium text-primary">{rupiah(r.hargaBaru)}</span>,
    },
    {
      key: "sel",
      header: "Selisih",
      align: "right",
      cell: (r) => {
        const d = r.hargaBaru - r.hargaLama;
        return (
          <span className={d >= 0 ? "text-success" : "text-destructive"}>
            {d >= 0 ? "+" : "−"}
            {rupiah(Math.abs(d)).replace("Rp ", "Rp ")}
          </span>
        );
      },
    },
    { key: "g", header: "Grade", cell: () => g },
  ];

  const karyawanCols: Column<Karyawan>[] = [
    { key: "nama", header: "Nama Karyawan", sortValue: (r) => r.nama, cell: (r) => r.nama },
    { key: "kontak", header: "Kontak", cell: (r) => r.kontak },
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
            deleteKaryawan(r.id);
            toast.success("Karyawan dihapus", { description: r.nama });
          }}
        >
          <Trash2 className="size-4 text-destructive" />
        </Button>
      ),
    },
  ];

  const eksportirCols: Column<Eksportir>[] = [
    { key: "nama", header: "Nama Perusahaan", sortValue: (r) => r.nama, cell: (r) => r.nama },
    { key: "kontak", header: "Kontak", cell: (r) => r.kontak },
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
            deleteEksportir(r.id);
            toast.success("Eksportir dihapus", { description: r.nama });
          }}
        >
          <Trash2 className="size-4 text-destructive" />
        </Button>
      ),
    },
  ];

  const tarifCards: { key: "kristal" | "brondol" | "uangMakan"; label: string; desc: string }[] = [
    { key: "kristal", label: "Tarif Gaji per Kg Gula Kristal", desc: "Dikalikan total kg kristal karyawan" },
    { key: "brondol", label: "Tarif Gaji per Kg Gula Brondol", desc: "Dikalikan total kg brondol karyawan" },
    {
      key: "uangMakan",
      label: "Uang Makan Harian",
      desc: "Dibayarkan per hari karyawan tercatat kerja",
    },
  ];

  return (
    <>
      <PageHeader title="Master Harga & Tarif" subtitle="Acuan harga beli, tarif produksi, dan data mitra" />

      <Tabs defaultValue="harga">
        <TabsList className="mb-4 flex-wrap">
          <TabsTrigger value="harga">Harga Beli per Grade</TabsTrigger>
          <TabsTrigger value="tarif">Tarif Produksi</TabsTrigger>
          <TabsTrigger value="data">Karyawan & Eksportir</TabsTrigger>
        </TabsList>

        <TabsContent value="harga" className="space-y-4">
          <div className="grid gap-4 md:grid-cols-3">
            {GRADES.map((g) => (
              <Card key={g} className="shadow-card">
                <CardHeader className="pb-2">
                  <CardTitle className="text-base">Grade {g}</CardTitle>
                </CardHeader>
                <CardContent>
                  <p className="text-3xl font-semibold tracking-tight">{rupiah(state.hargaBeli[g])}</p>
                  <p className="text-xs text-muted-foreground">per kilogram</p>
                  <Button className="mt-4 w-full" variant="outline" onClick={() => bukaHarga(g)}>
                    Ubah Harga
                  </Button>
                </CardContent>
              </Card>
            ))}
          </div>

          <div className="grid gap-4 md:grid-cols-3">
            {GRADES.map((g) => {
              const rows = state.riwayatHarga
                .filter((r) => r.grade === g)
                .sort((a, b) => (a.tanggal < b.tanggal ? 1 : -1));
              return (
                <Card key={g} className="overflow-hidden shadow-card">
                  <CardHeader className="pb-2">
                    <CardTitle className="text-sm">Riwayat Harga {g}</CardTitle>
                  </CardHeader>
                  <CardContent className="px-0 pb-0">
                    <DataTable
                      rows={rows}
                      columns={riwayatCols(g).slice(0, 3)}
                      rowKey={(r) => r.id}
                      empty={
                        <EmptyState
                          title="Belum ada perubahan harga"
                          description={`Harga ${g} belum pernah diubah.`}
                        />
                      }
                    />
                  </CardContent>
                </Card>
              );
            })}
          </div>
        </TabsContent>

        <TabsContent value="tarif">
          <div className="grid gap-4 md:grid-cols-3">
            {tarifCards.map((t) => (
              <Card key={t.key} className="shadow-card">
                <CardHeader className="pb-2">
                  <CardTitle className="flex items-center gap-2 text-base">
                    <Wallet className="size-4 text-primary" /> {t.label}
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <p className="text-3xl font-semibold tracking-tight">{rupiah(state.tarif[t.key])}</p>
                  <p className="text-xs text-muted-foreground">{t.desc}</p>
                  <Button className="mt-4 w-full" variant="outline" onClick={() => bukaTarif(t.key)}>
                    Ubah Tarif
                  </Button>
                </CardContent>
              </Card>
            ))}
          </div>
        </TabsContent>

        <TabsContent value="data" className="grid gap-4 lg:grid-cols-2">
          <Card className="overflow-hidden shadow-card">
            <CardHeader>
              <CardTitle className="text-base">Data Karyawan ({state.karyawan.length})</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4 px-0">
              <div className="flex flex-wrap gap-2 px-4">
                <Input
                  placeholder="Nama karyawan"
                  value={kForm.nama}
                  onChange={(e) => setKForm({ ...kForm, nama: e.target.value })}
                  className="flex-1"
                />
                <Input
                  placeholder="Kontak"
                  value={kForm.kontak}
                  onChange={(e) => setKForm({ ...kForm, kontak: e.target.value })}
                  className="flex-1"
                />
                <Button
                  onClick={() => {
                    if (!kForm.nama.trim() || !kForm.kontak.trim()) {
                      toast.error("Nama dan kontak karyawan wajib diisi");
                      return;
                    }
                    addKaryawan({ nama: kForm.nama.trim(), kontak: kForm.kontak.trim() });
                    toast.success("Karyawan ditambahkan", { description: kForm.nama });
                    setKForm({ nama: "", kontak: "" });
                  }}
                >
                  <Plus className="size-4" />
                </Button>
              </div>
              <div className="max-h-[420px] overflow-y-auto">
                <DataTable rows={state.karyawan} columns={karyawanCols} rowKey={(r) => r.id} />
              </div>
            </CardContent>
          </Card>

          <Card className="overflow-hidden shadow-card">
            <CardHeader>
              <CardTitle className="text-base">Data Eksportir ({state.eksportir.length})</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4 px-0">
              <div className="flex flex-wrap gap-2 px-4">
                <Input
                  placeholder="Nama perusahaan"
                  value={eForm.nama}
                  onChange={(e) => setEForm({ ...eForm, nama: e.target.value })}
                  className="flex-1"
                />
                <Input
                  placeholder="Kontak"
                  value={eForm.kontak}
                  onChange={(e) => setEForm({ ...eForm, kontak: e.target.value })}
                  className="flex-1"
                />
                <Button
                  onClick={() => {
                    if (!eForm.nama.trim() || !eForm.kontak.trim()) {
                      toast.error("Nama perusahaan dan kontak wajib diisi");
                      return;
                    }
                    addEksportir({ nama: eForm.nama.trim(), kontak: eForm.kontak.trim() });
                    toast.success("Eksportir ditambahkan", { description: eForm.nama });
                    setEForm({ nama: "", kontak: "" });
                  }}
                >
                  <Plus className="size-4" />
                </Button>
              </div>
              <DataTable rows={state.eksportir} columns={eksportirCols} rowKey={(r) => r.id} />
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      <Dialog open={hargaDialog !== null} onOpenChange={(v) => !v && setHargaDialog(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Ubah Harga Beli — {hargaDialog}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-2">
              <Label>Harga Baru per Kg</Label>
              <Input
                type="number"
                min={0}
                value={hargaBaru}
                onChange={(e) => setHargaBaru(e.target.value)}
              />
            </div>
            <div className="space-y-2">
              <Label>Tanggal Berlaku</Label>
              <Input
                type="date"
                value={tanggalBerlaku}
                onChange={(e) => setTanggalBerlaku(e.target.value)}
              />
            </div>
            {errHarga && <p className="text-sm text-destructive">{errHarga}</p>}
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setHargaDialog(null)}>
              Batal
            </Button>
            <Button onClick={simpanHarga}>Simpan Harga</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={tarifDialog !== null} onOpenChange={(v) => !v && setTarifDialog(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Ubah Tarif</DialogTitle>
          </DialogHeader>
          <div className="space-y-2">
            <Label>Nilai Baru (Rp)</Label>
            <Input type="number" min={0} value={tarifValue} onChange={(e) => setTarifValue(e.target.value)} />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setTarifDialog(null)}>
              Batal
            </Button>
            <Button onClick={simpanTarif}>Simpan</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
