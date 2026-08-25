/**
 * Entry client-only (mode SPA) untuk self-hosting di VPS.
 *
 * Alur Lovable memakai TanStack Start + Nitro (SSR, target Cloudflare) lewat
 * `vite.config.ts`. File ini jalur kedua yang berdiri sendiri: seluruh halaman
 * dirender di browser dan semua data diambil dari API Laravel, jadi hasil
 * build-nya file statis biasa yang cukup disajikan Nginx.
 *
 * Dibangun dengan: npm run build:spa
 */
import { StrictMode, type ReactNode } from "react";
import { createRoot } from "react-dom/client";
import { HeadContent, RouterProvider } from "@tanstack/react-router";

import { routeTree } from "./routeTree.gen";
import { getRouter } from "./router";
import "./styles.css";

/**
 * Mengganti shellComponent root route dengan versi khusus SPA.
 *
 * Root route mendefinisikan shell yang merender <html><head><body>. Itu benar
 * untuk SSR (Start memakainya menyusun dokumen), tapi di mode SPA dokumennya
 * sudah disediakan index.html. Shell diganti dengan versi yang hanya merender
 * <HeadContent /> supaya judul & meta per-halaman tetap jalan tanpa ikut
 * menggambar tag dokumen.
 *
 * Ditaruh di sini (bukan di __root.tsx) supaya jalur SSR Lovable tetap utuh.
 */
function SpaShell({ children }: { children: ReactNode }) {
  return (
    <>
      <HeadContent />
      {children}
    </>
  );
}

(routeTree.options as { shellComponent?: unknown }).shellComponent = SpaShell;

const router = getRouter();
const container = document.getElementById("root");

if (!container) {
  throw new Error("Elemen #root tidak ditemukan di index.html.");
}

createRoot(container).render(
  <StrictMode>
    <RouterProvider router={router} />
  </StrictMode>,
);
