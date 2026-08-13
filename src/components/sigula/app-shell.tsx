import { useState, type ReactNode } from "react";
import { Link, useLocation } from "@tanstack/react-router";
import {
  BarChart3,
  Boxes,
  ChevronLeft,
  Factory,
  LayoutDashboard,
  LogOut,
  Menu,
  ShoppingCart,
  Sprout,
  Tags,
  TrendingUp,
  Users,
  Wallet,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

const MENU = [
  { to: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
  { to: "/petani", label: "Data Petani", icon: Sprout },
  { to: "/master", label: "Master Harga & Tarif", icon: Tags },
  { to: "/pembelian", label: "Pembelian Bahan", icon: ShoppingCart },
  { to: "/stok", label: "Manajemen Stok", icon: Boxes },
  { to: "/produksi", label: "Produksi (Sesi Tungku)", icon: Factory },
  { to: "/penggajian", label: "Penggajian", icon: Users },
  { to: "/penjualan", label: "Penjualan", icon: TrendingUp },
  { to: "/keuangan", label: "Keuangan", icon: Wallet },
] as const;

export function AppShell({ children }: { children: ReactNode }) {
  const [collapsed, setCollapsed] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);
  const { pathname } = useLocation();

  const nav = (
    <nav className="flex flex-1 flex-col gap-1 overflow-y-auto p-3">
      {MENU.map((m) => {
        const active = pathname === m.to;
        const Icon = m.icon;
        return (
          <Link
            key={m.to}
            to={m.to}
            onClick={() => setMobileOpen(false)}
            className={cn(
              "flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors",
              active
                ? "bg-sidebar-primary text-sidebar-primary-foreground shadow-card"
                : "text-sidebar-foreground/80 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground",
            )}
            title={m.label}
          >
            <Icon className="size-[18px] shrink-0" />
            {!collapsed && <span className="truncate">{m.label}</span>}
          </Link>
        );
      })}
    </nav>
  );

  const brand = (
    <div className="flex items-center gap-3 border-b border-sidebar-border px-4 py-4">
      <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-sidebar-primary text-sidebar-primary-foreground">
        <BarChart3 className="size-5" />
      </span>
      {!collapsed && (
        <div className="min-w-0">
          <p className="truncate font-semibold leading-tight text-sidebar-foreground">SIGULA</p>
          <p className="truncate text-[11px] text-sidebar-foreground/60">PT Nira Sari Murni</p>
        </div>
      )}
    </div>
  );

  return (
    <div className="flex min-h-screen bg-background">
      {/* desktop sidebar */}
      <aside
        className={cn(
          "sticky top-0 hidden h-screen shrink-0 flex-col bg-sidebar transition-all duration-300 md:flex",
          collapsed ? "w-[76px]" : "w-[264px]",
        )}
      >
        {brand}
        {nav}
        <div className="border-t border-sidebar-border p-3">
          <button
            onClick={() => setCollapsed((c) => !c)}
            className="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm text-sidebar-foreground/70 transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
          >
            <ChevronLeft className={cn("size-[18px] transition-transform", collapsed && "rotate-180")} />
            {!collapsed && <span>Perkecil menu</span>}
          </button>
        </div>
      </aside>

      {/* mobile drawer */}
      {mobileOpen && (
        <div className="fixed inset-0 z-50 md:hidden">
          <div className="absolute inset-0 bg-foreground/40" onClick={() => setMobileOpen(false)} />
          <aside className="relative flex h-full w-[264px] flex-col bg-sidebar">
            {brand}
            {nav}
          </aside>
        </div>
      )}

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="sticky top-0 z-40 flex items-center justify-between gap-3 border-b bg-card/90 px-4 py-3 backdrop-blur md:px-6">
          <div className="flex items-center gap-3">
            <Button
              variant="ghost"
              size="icon"
              className="md:hidden"
              onClick={() => setMobileOpen(true)}
              aria-label="Buka menu"
            >
              <Menu className="size-5" />
            </Button>
            <div>
              <p className="text-sm font-semibold leading-tight">Sistem Informasi Gula Terintegrasi</p>
              <p className="text-xs text-muted-foreground">PT Nira Sari Murni</p>
            </div>
          </div>
          <div className="flex items-center gap-3">
            <span className="rounded-full bg-warning px-2.5 py-1 text-[10px] font-bold tracking-wider text-warning-foreground">
              DEMO
            </span>
            <div className="hidden text-right sm:block">
              <p className="text-sm font-medium leading-tight">Budi Santoso</p>
              <p className="text-xs text-muted-foreground">Admin Operasional</p>
            </div>
            <span className="flex size-9 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground">
              BS
            </span>
            <Button asChild variant="ghost" size="icon" aria-label="Keluar">
              <Link to="/">
                <LogOut className="size-4" />
              </Link>
            </Button>
          </div>
        </header>
        <main className="flex-1 px-4 py-6 md:px-6 md:py-8">
          <div className="mx-auto w-full max-w-[1400px]">{children}</div>
        </main>
      </div>
    </div>
  );
}
