import { useState } from "react";
import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { BarChart3, Loader2, Lock, Mail } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ApiError } from "@/lib/api-client";
import { useAuth } from "@/store/auth-store";

export const Route = createFileRoute("/")({
  head: () => ({
    meta: [
      { title: "Masuk — SIGULA | PT Nira Sari Murni" },
      {
        name: "description",
        content:
          "Masuk ke SIGULA, sistem informasi gula terintegrasi PT Nira Sari Murni untuk pembelian bahan, produksi tungku, penggajian, dan laporan keuangan.",
      },
      { property: "og:title", content: "Masuk — SIGULA | PT Nira Sari Murni" },
      {
        property: "og:description",
        content: "Satu platform untuk pengadaan gula: petani, produksi, penggajian, penjualan, dan keuangan.",
      },
    ],
  }),
  component: LoginPage,
});

function LoginPage() {
  const navigate = useNavigate();
  const { login } = useAuth();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!email.trim() || !password.trim()) {
      setError("Email dan password wajib diisi.");
      return;
    }
    setError("");
    setLoading(true);
    try {
      await login({ email: email.trim(), password, namaPerangkat: "sigula-web" });
      toast.success("Berhasil masuk", { description: "Selamat datang kembali." });
      navigate({ to: "/dashboard" });
    } catch (err) {
      const message =
        err instanceof ApiError
          ? (err.firstFieldError ?? err.message)
          : "Tidak bisa terhubung ke server. Coba lagi.";
      setError(message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-10">
      <div className="absolute inset-0 -z-10 bg-gradient-to-br from-cream via-background to-secondary" />
      <div className="absolute -left-24 -top-24 -z-10 size-72 rounded-full bg-primary/10 blur-3xl" />
      <div className="absolute -bottom-24 -right-24 -z-10 size-80 rounded-full bg-warning/20 blur-3xl" />

      <div className="w-full max-w-md">
        <div className="mb-6 flex flex-col items-center text-center">
          <span className="flex size-14 items-center justify-center rounded-2xl bg-primary text-primary-foreground shadow-pop">
            <BarChart3 className="size-7" />
          </span>
          <h1 className="mt-4 text-3xl font-bold tracking-tight">SIGULA</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Sistem Informasi Gula Terintegrasi — PT Nira Sari Murni
          </p>
        </div>

        <form
          onSubmit={submit}
          className="rounded-2xl border bg-card p-6 shadow-card sm:p-8"
          noValidate
        >
          <div className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="email">Email</Label>
              <div className="relative">
                <Mail className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                  id="email"
                  type="email"
                  className="pl-9"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="nama@perusahaan.co.id"
                  disabled={loading}
                  autoComplete="username"
                />
              </div>
            </div>
            <div className="space-y-2">
              <Label htmlFor="password">Password</Label>
              <div className="relative">
                <Lock className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                  id="password"
                  type="password"
                  className="pl-9"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="••••••••"
                  disabled={loading}
                  autoComplete="current-password"
                />
              </div>
            </div>
            {error && <p className="text-sm text-destructive">{error}</p>}
            <Button type="submit" className="w-full" size="lg" disabled={loading}>
              {loading && <Loader2 className="size-4 animate-spin" />}
              Masuk
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
