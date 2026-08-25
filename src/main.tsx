/**
 * Entry client-only (mode SPA) untuk self-hosting di VPS.
 *
 * Alur Lovable memakai TanStack Start + Nitro (SSR, target Cloudflare) lewat
 * `vite.config.ts`. File ini adalah jalur kedua yang berdiri sendiri: seluruh
 * halaman dirender di browser dan semua data diambil dari API Laravel, jadi
 * hasil build-nya file statis biasa yang cukup disajikan Nginx.
 *
 * Dibangun dengan: npm run build:spa
 */
import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import { RouterProvider } from "@tanstack/react-router";

import { getRouter } from "./router";
import "./styles.css";

const router = getRouter();
const container = document.getElementById("root");

if (!container) {
  throw new Error('Elemen #root tidak ditemukan di index.html.');
}

createRoot(container).render(
  <StrictMode>
    <RouterProvider router={router} />
  </StrictMode>,
);
