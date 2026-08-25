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
import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import { RouterProvider } from "@tanstack/react-router";

import { routeTree } from "./routeTree.gen";
import { getRouter } from "./router";
import "./styles.css";

/**
 * Menonaktifkan shellComponent milik root route.
 *
 * Root route mendefinisikan `shellComponent` yang merender <html><head><body> —
 * itu benar untuk SSR, karena Start memakainya menyusun dokumen. Tapi router
 * merender shell tanpa memeriksa server/klien (lihat Match.js: `route.isRoot ?
 * route.options.shellComponent ?? SafeFragment`), sehingga di mode SPA dokumen
 * lengkap itu ikut digambar DI DALAM <div id="root"> — HTML bersarang yang
 * tidak valid dan membuat layout kacau.
 *
 * Dimatikan di sini (bukan di __root.tsx) supaya jalur SSR Lovable tetap utuh.
 */
(routeTree.options as { shellComponent?: unknown }).shellComponent = undefined;

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
