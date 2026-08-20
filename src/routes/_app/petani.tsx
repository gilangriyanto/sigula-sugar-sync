import { useEffect, useState } from "react";
import { createFileRoute } from "@tanstack/react-router";
import { Loader2, Pencil, Plus, Trash2 } from "lucide-react";
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  DataTable,
  EmptyState,
  PageHeader,
  SearchInput,
  type Column,
} from "@/components/sigula/ui-bits";
import { angka, rupiah } from "@/lib/format";
import { ApiError } from "@/lib/api-client";
import type { Petani, PetaniPayload } from "@/lib/api/petani";
import { useHapusPetani, usePetaniList, useTambahPetani, useUbahPetani } from "@/hooks/use-petani";

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
      {
        property: "og:description",
        content: "Database petani mitra beserta riwayat nilai transaksi.",
      },
    ],
  }),
  component: PetaniPage,
});

interface FormState {
  nama: string;
  status: "Member" | "Non-Member";
  kontak: string;
  alamat: string;
}

const emptyForm: FormState = {
  nama: "",
  status: "Member",
  kontak: "",
  alamat: "",
};

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

function PetaniPage() {
  const [q, setQ] = useState("");
  const [qDebounced, setQDebounced] = useState("");

  useEffect(() => {
    const t = setTimeout(() => setQDebounced(q.trim()), 300);
    return () => clearTimeout(t);
  }, [q]);

  const { data: rows = [], isLoading, isError } = usePetaniList({ q: qDebounced || undefined });
  const tambahPetani = useTambahPetani();
  const ubahPetani = useUbahPetani();
  const hapusPetani = useHapusPetani();

  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<Petani | null>(null);
  const [form, setForm] = useState<FormState>(emptyForm);
  const [err, setErr] = useState<string | null>(null);

  const openTambah = () => {
    setEditing(null);
    setForm(emptyForm);
    setErr(null);
    setOpen(true);
  };

  const openEdit = (p: Petani) => {
    setEditing(p);
    setForm({ nama: p.nama, status: p.status, kontak: p.kontak, alamat: p.alamat });
    setErr(null);
    setOpen(true);
  };

  const simpan = async () => {
    if (!form.nama.trim()) return setErr("Nama petani wajib diisi.");
    const payload: PetaniPayload = {
      nama: form.nama.trim(),
      status: form.status,
      kontak: form.kontak.trim() || undefined,
      alamat: form.alamat.trim() || undefined,
    };
    try {
      if (editing) {
        await ubahPetani.mutateAsync({ id: editing.id, payload });
        toast.success("Data petani diperbarui", { description: payload.nama });
      } else {
        await tambahPetani.mutateAsync(payload);
        toast.success("Petani baru ditambahkan", { description: payload.nama });
      }
      setOpen(false);
    } catch (e) {
      setErr(
        apiErrorMessage(e, editing ? "Gagal mengubah data petani." : "Gagal menambah petani."),
      );
    }
  };

  const hapus = async (p: Petani) => {
    try {
      await hapusPetani.mutateAsync(p.id);
      toast.success("Data petani dihapus", { description: p.nama });
    } catch (e) {
      toast.error(apiErrorMessage(e, "Gagal menghapus petani."));
    }
  };

  const saving = tambahPetani.isPending || ubahPetani.isPending;

  const cols: Column<Petani>[] = [
    {
      key: "nama",
      header: "Nama",
      sortValue: (r) => r.nama,
      cell: (r) => <span className="font-medium">{r.nama}</span>,
    },
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
      cell: (r) =>
        r.labelMember ? r.labelMember : <span className="text-muted-foreground">—</span>,
    },
    { key: "kontak", header: "Kontak", cell: (r) => r.kontak || "-" },
    {
      key: "alamat",
      header: "Alamat",
      cell: (r) => <span className="text-muted-foreground">{r.alamat || "-"}</span>,
    },
    {
      key: "trx",
      header: "Total Transaksi",
      align: "right",
      sortValue: (r) => r.totalNilai,
      cell: (r) => (
        <div>
          <p className="font-medium">{rupiah(r.totalNilai)}</p>
          <p className="text-xs text-muted-foreground">{angka(r.totalTransaksi)} transaksi</p>
        </div>
      ),
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
        subtitle={isLoading ? "Memuat…" : `${rows.length} petani mitra terdaftar`}
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
                        setForm({ ...form, status: v })
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
                      <Label>Nomor Member</Label>
                      <div className="flex h-9 items-center rounded-md border bg-muted px-3 text-sm text-muted-foreground">
                        {editing?.labelMember || "Dibuat otomatis saat disimpan"}
                      </div>
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
                <Button onClick={simpan} disabled={saving}>
                  {saving && <Loader2 className="size-4 animate-spin" />}
                  Simpan
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        }
      />

      <Card className="overflow-hidden shadow-card">
        <CardContent className="space-y-4 px-0 py-4">
          <div className="px-4">
            <SearchInput
              value={q}
              onChange={setQ}
              placeholder="Cari nama, nomor member, atau kontak..."
            />
          </div>
          {isError && (
            <p className="px-4 text-sm text-destructive">
              Gagal memuat data petani. Coba muat ulang halaman.
            </p>
          )}
          {isLoading ? (
            <LoadingRow />
          ) : (
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
          )}
        </CardContent>
      </Card>
    </>
  );
}
