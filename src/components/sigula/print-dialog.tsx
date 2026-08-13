import type { ReactNode } from "react";
import { Printer } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";

export function PrintDialog({
  open,
  onOpenChange,
  title,
  children,
}: {
  open: boolean;
  onOpenChange: (v: boolean) => void;
  title: string;
  children: ReactNode;
}) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
        </DialogHeader>
        <div className="rounded-xl border bg-card p-5 text-sm">
          <div className="mb-4 border-b pb-3 text-center">
            <p className="text-base font-semibold tracking-tight">PT NIRA SARI MURNI</p>
            <p className="text-xs text-muted-foreground">
              SIGULA — Sistem Informasi Gula Terintegrasi (Dokumen Demo)
            </p>
          </div>
          {children}
          <p className="mt-5 border-t pt-3 text-center text-[11px] text-muted-foreground">
            Dokumen ini dicetak dari sistem SIGULA.
          </p>
        </div>
        <DialogFooter className="no-print">
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Tutup
          </Button>
          <Button onClick={() => window.print()}>
            <Printer className="mr-2 size-4" /> Cetak
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export function PrintRow({ label, value, strong }: { label: string; value: ReactNode; strong?: boolean }) {
  return (
    <div className={`flex justify-between gap-4 py-1 ${strong ? "font-semibold" : ""}`}>
      <span className="text-muted-foreground">{label}</span>
      <span className="text-right">{value}</span>
    </div>
  );
}
