import { useMemo, useState, type ReactNode } from "react";
import { ArrowDown, ArrowUp, Inbox, Search } from "lucide-react";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { cn } from "@/lib/utils";

export function PageHeader({
  title,
  subtitle,
  action,
}: {
  title: string;
  subtitle?: string;
  action?: ReactNode;
}) {
  return (
    <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
        {subtitle && <p className="mt-1 text-sm text-muted-foreground">{subtitle}</p>}
      </div>
      {action}
    </div>
  );
}

export function StatCard({
  label,
  value,
  hint,
  icon,
  tone = "default",
}: {
  label: string;
  value: ReactNode;
  hint?: ReactNode;
  icon?: ReactNode;
  tone?: "default" | "success" | "warning" | "primary";
}) {
  const toneRing =
    tone === "success"
      ? "bg-success/10 text-success"
      : tone === "warning"
        ? "bg-warning/20 text-warning-foreground"
        : tone === "primary"
          ? "bg-primary/10 text-primary"
          : "bg-secondary text-secondary-foreground";
  return (
    <Card className="shadow-card">
      <CardContent className="flex items-start gap-4 p-5">
        {icon && (
          <span className={cn("flex size-10 shrink-0 items-center justify-center rounded-xl", toneRing)}>
            {icon}
          </span>
        )}
        <div className="min-w-0">
          <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
          <p className="mt-1 truncate text-2xl font-semibold tracking-tight">{value}</p>
          {hint && <div className="mt-1 text-xs text-muted-foreground">{hint}</div>}
        </div>
      </CardContent>
    </Card>
  );
}

export function EmptyState({
  title = "Belum ada data",
  description = "Data akan muncul di sini setelah kamu menambahkannya.",
  action,
}: {
  title?: string;
  description?: string;
  action?: ReactNode;
}) {
  return (
    <div className="flex flex-col items-center justify-center gap-3 px-6 py-14 text-center">
      <span className="flex size-14 items-center justify-center rounded-2xl bg-secondary text-primary">
        <Inbox className="size-6" />
      </span>
      <div>
        <p className="font-medium">{title}</p>
        <p className="mt-1 text-sm text-muted-foreground">{description}</p>
      </div>
      {action}
    </div>
  );
}

export function SearchInput({
  value,
  onChange,
  placeholder = "Cari...",
  className,
}: {
  value: string;
  onChange: (v: string) => void;
  placeholder?: string;
  className?: string;
}) {
  return (
    <div className={cn("relative w-full sm:w-64", className)}>
      <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
      <Input
        className="pl-9"
        value={value}
        placeholder={placeholder}
        onChange={(e) => onChange(e.target.value)}
      />
    </div>
  );
}

export interface Column<T> {
  key: string;
  header: string;
  cell: (row: T) => ReactNode;
  sortValue?: (row: T) => string | number;
  align?: "left" | "right" | "center";
  className?: string;
}

export function DataTable<T>({
  rows,
  columns,
  empty,
  initialSort,
  rowKey,
}: {
  rows: T[];
  columns: Column<T>[];
  empty?: ReactNode;
  initialSort?: { key: string; dir: "asc" | "desc" };
  rowKey: (row: T, i: number) => string;
}) {
  const [sort, setSort] = useState<{ key: string; dir: "asc" | "desc" } | null>(initialSort ?? null);

  const sorted = useMemo(() => {
    if (!sort) return rows;
    const col = columns.find((c) => c.key === sort.key);
    if (!col?.sortValue) return rows;
    const out = [...rows].sort((a, b) => {
      const va = col.sortValue!(a);
      const vb = col.sortValue!(b);
      if (typeof va === "number" && typeof vb === "number") return va - vb;
      return String(va).localeCompare(String(vb), "id");
    });
    return sort.dir === "asc" ? out : out.reverse();
  }, [rows, sort, columns]);

  const toggle = (key: string) =>
    setSort((s) =>
      s && s.key === key ? { key, dir: s.dir === "asc" ? "desc" : "asc" } : { key, dir: "asc" },
    );

  if (rows.length === 0) return <>{empty ?? <EmptyState />}</>;

  return (
    <div className="w-full overflow-x-auto">
      <table className="w-full min-w-[640px] border-collapse text-sm">
        <thead>
          <tr className="border-b bg-cream/60">
            {columns.map((c) => (
              <th
                key={c.key}
                className={cn(
                  "whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground",
                  c.align === "right" ? "text-right" : c.align === "center" ? "text-center" : "text-left",
                )}
              >
                {c.sortValue ? (
                  <button
                    type="button"
                    onClick={() => toggle(c.key)}
                    className={cn(
                      "inline-flex items-center gap-1 transition-colors hover:text-primary",
                      sort?.key === c.key && "text-primary",
                    )}
                  >
                    {c.header}
                    {sort?.key === c.key ? (
                      sort.dir === "asc" ? (
                        <ArrowUp className="size-3" />
                      ) : (
                        <ArrowDown className="size-3" />
                      )
                    ) : null}
                  </button>
                ) : (
                  c.header
                )}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {sorted.map((row, i) => (
            <tr key={rowKey(row, i)} className="border-b last:border-0 hover:bg-cream/50">
              {columns.map((c) => (
                <td
                  key={c.key}
                  className={cn(
                    "px-4 py-3 align-middle",
                    c.align === "right" ? "text-right" : c.align === "center" ? "text-center" : "text-left",
                    c.className,
                  )}
                >
                  {c.cell(row)}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export function ProgressCircle({ value, label }: { value: number; label?: string }) {
  const clamped = Math.max(0, Math.min(100, value));
  const r = 52;
  const c = 2 * Math.PI * r;
  return (
    <div className="relative flex size-36 items-center justify-center">
      <svg viewBox="0 0 120 120" className="size-36 -rotate-90">
        <circle cx="60" cy="60" r={r} className="fill-none stroke-secondary" strokeWidth="12" />
        <circle
          cx="60"
          cy="60"
          r={r}
          className="fill-none stroke-primary transition-all duration-700"
          strokeWidth="12"
          strokeLinecap="round"
          strokeDasharray={c}
          strokeDashoffset={c - (clamped / 100) * c}
        />
      </svg>
      <div className="absolute text-center">
        <p className="text-2xl font-semibold">{clamped.toFixed(1)}%</p>
        {label && <p className="text-xs text-muted-foreground">{label}</p>}
      </div>
    </div>
  );
}
