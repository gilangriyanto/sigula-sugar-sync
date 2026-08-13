import { useMemo, useState } from "react";
import { createFileRoute } from "@tanstack/react-router";
import { Pencil, Plus, Trash2 } from "lucide-react";
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
  DialogTrigger,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import {
  DataTable,
  EmptyState,
  PageHeader,
  SearchInput,
  type Column,
} from "@/components/sigula/ui-bits";
import { angka, rupiah } from "@/lib/format";
import type { Petani } from "@/lib/sigula-types";
import { useSigula } from "@/store/sigula-store";

export const Route = createFileRoute("/_app/petani")({
  head: () => ({
    meta: [
      { title: "Data Petani — SIGULA" },
      {
        name: "description",
        content:
          "Kelola data petani mitra PT Nira Sari Murni: status member, nomor member, kontak, alamat, dan total transaksi pembelian bahan.",
      },
      { property: "og:title", content: "Data Petani — SIGULA" },
      { property: "og:description", content: "Database petani mitra beserta riwayat nilai transaksi." },
    ],
  }),
  component: PetaniPage,
});

interface FormState {
  nama: string;
  status: "Member" | "Non-Member";
  nomorMember: string;
  kontak: string;
  alamat: string;
}

const emptyForm: FormState = {
  nama: "",
  status: "Member",
  nomorMember: "",
  kontak: "",
  alamat: "",
};

function PetaniPage() {
  const { state, addPetani, updatePetani, deletePetani } = useSigula();
  const [q, setQ] = useState("");
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<Petani | null>(null);
  const [form, setForm] = useState<FormState>(emptyForm);
  const [err, setErr] = useState<string | null>(null);

  const generateNomor = () => {
    const used = state.petani.map((p) => Number(p.nomorMember)).filter((n) => !Number.isNaN(n) && n > 0);
    const next = (used.length ? Math.max(...used) : 200) + 1;
    return String(Math.min(999, next)).padStart(3, "0");
  };

  const totalTransaksi = useMemo(() => {
    const map = new Map<string, { nilai: number; jumlah: number }>();
    state.pembelian.forEach((p) => {
      const cur = map.get(p.petaniId) ?? { nilai: 0, jumlah: 0 };
      cur.nilai += p.total;
      cur.jumlah += 1;
      map.set(p.petaniId, cur);
    });
    return map;
  }, [state.pembelian]);

  const rows = useMemo(() => {
    const term = q.trim().toLowerCase();
    if (!term) return state.petani;
    return state.petani.filter(
      (p) => p.nama.toLowerCase().includes(term) || p.nomorMember.includes(term),
    );
  }, [state.petani, q]);

  const openTambah = () => {
    setEditing(null);
    setForm({ ...emptyForm, nomorMember: generateNomor() });
    setErr(null);
    setOpen(true);
  };

  const openEdit = (p: Petani) => {
    setEditing(p);
    setForm({
      nama: p.nama,
      status: p.status,
      nomorMember: p.nomorMember || generateNomor(),
      kontak: p.kontak,
      alamat: p.alamat,
    });
    setErr(null);
    setOpen(true);
  };

  const simpan = () => {
    if (!form.nama.trim()) return setErr("Nama petani wajib diisi.");
    if (!form.kontak.trim()) return setErr("Kontak wajib diisi.");
    const payload = {
      nama: form.nama.trim(),
      status: form.status,
      nomorMember: form.status === "Member" ? form.nomorMember : "",
      kontak: form.kontak.trim(),
      alamat: form.alamat.trim(),
    };
    if (editing) {
      updatePetani(editing.id, payload);
      toast.success("Data petani diperbarui", { description: payload.nama });
    } else {
      addPetani(payload);
      toast.success("Petani baru ditambahkan", { description: payload.nama });
    }
    setOpen(false);
  };

  const hapus = (p: Petani) => {
    deletePetani(p.id);
    toast.success("Data petani dihapus", { description: p.nama });
  };

  const cols: Column<Petani>[] = [
    { key: "nama", header: "Nama", sortValue: (r) => r.nama, cell: (r) => <span className="font-medium">{r.nama}</span> },
    {
      key: "status",
      header: "Status",
      sortValue: (r) => r.status,
      cell: (r) =>
        r.status === "Member" ? (
          <Badge className="bg-success/15 text-success hover:bg-success/15">Member</Badge>
        ) : (
          <Badge variant="secondary">Non-Member</Badge>
        ),
    },
    {
      key: "nomor",
      header: "Nomor Member",
      cell: (r) => (r.nomorMember ? `Petani ${r.nomorMember}` : <span className="text-muted-foreground">—</span>),
    },
    { key: "kontak", header: "Kontak", cell: (r) => r.kontak },
    { key: "alamat", header: "Alamat", cell: (r) => <span className="text-muted-foreground">{r.alamat}</span> },
    {
      key: "trx",
      header: "Total Transaksi",
      align: "right",
      sortValue: (r) => totalTransaksi.get(r.id)?.nilai ?? 0,
      cell: (r) => {
        const t = totalTransaksi.get(r.id);
        return (
          <div>
            <p className="font-medium">{rupiah(t?.nilai ?? 0)}</p>
            <p className="text-xs text-muted-foreground">{angka(t?.jumlah ?? 0)} transaksi</p>
          </div>
        );
      },
    },
    {
      key: "aksi",
      header: "Aksi",
      align: "right",
      cell: (r) => (
        <div className="flex justify-end gap-1">
          <Button variant="ghost" size="icon" aria-label="Ubah" onClick={() => openEdit(r)}>
            <Pencil className="size-4" />
          </Button>
          <Button variant="ghost" size="icon" aria-label="Hapus" onClick={() => hapus(r)}>
            <Trash2 className="size-4 text-destructive" />
          </Button>
        </div>
      ),
    },
  ];

  return (
    <>
      <PageHeader
        title="Data Petani"
        subtitle={`${state.petani.length} petani mitra terdaftar`}
        action={
          <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
              <Button onClick={openTambah}>
                <Plus className="mr-2 size-4" /> Tambah Petani
              </Button>
            </DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>{editing ? "Ubah Data Petani" : "Tambah Petani"}</DialogTitle>
              </DialogHeader>
              <div className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="nama">Nama Petani</Label>
                  <Input
                    id="nama"
                    value={form.nama}
                    onChange={(e) => setForm({ ...form, nama: e.target.value })}
                    placeholder="Contoh: Sukirman"
                  />
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                  <div className="space-y-2">
                    <Label>Status</Label>
                    <Select
                      value={form.status}
                      onValueChange={(v: "Member" | "Non-Member") =>
                        setForm({
                          ...form,
                          status: v,
                          nomorMember: v === "Member" ? form.nomorMember || generateNomor() : "",
                        })
                      }
                    >
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="Member">Member</SelectItem>
                        <SelectItem value="Non-Member">Non-Member</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  {form.status === "Member" && (
                    <div className="space-y-2">
                      <Label htmlFor="nomor">Nomor Member</Label>
                      <Input id="nomor" value={form.nomorMember} readOnly className="bg-muted" />
                      <p className="text-xs text-muted-foreground">Otomatis: Petani {form.nomorMember}</p>
                    </div>
                  )}
                </div>
                <div className="space-y-2">
                  <Label htmlFor="kontak">Kontak</Label>
                  <Input
                    id="kontak"
                    value={form.kontak}
                    onChange={(e) => setForm({ ...form, kontak: e.target.value })}
                    placeholder="0812-3456-7890"
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="alamat">Alamat</Label>
                  <Input
                    id="alamat"
                    value={form.alamat}
                    onChange={(e) => setForm({ ...form, alamat: e.target.value })}
                    placeholder="Desa, Kecamatan"
                  />
                </div>
                {err && <p className="text-sm text-destructive">{err}</p>}
              </div>
              <DialogFooter>
                <Button variant="outline" onClick={() => setOpen(false)}>
                  Batal
                </Button>
                <Button onClick={simpan}>Simpan</Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        }
      />

      <Card className="overflow-hidden shadow-card">
        <CardContent className="space-y-4 px-0 py-4">
          <div className="px-4">
            <SearchInput value={q} onChange={setQ} placeholder="Cari nama atau nomor member..." />
          </div>
          <DataTable
            rows={rows}
            columns={cols}
            rowKey={(r) => r.id}
            initialSort={{ key: "nama", dir: "asc" }}
            empty={
              <EmptyState
                title="Petani tidak ditemukan"
                description="Coba kata kunci lain, atau tambahkan petani baru."
                action={
                  <Button onClick={openTambah}>
                    <Plus className="mr-2 size-4" /> Tambah Petani
                  </Button>
                }
              />
            }
          />
        </CardContent>
      </Card>
    </>
  );
}
