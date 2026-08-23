/**
 * Konfigurasi build SPA untuk self-hosting (VPS + Nginx).
 *
 * Sengaja terpisah dari `vite.config.ts` supaya alur Lovable (TanStack Start +
 * Nitro, target Cloudflare) tidak terganggu. Config ini tidak memakai plugin
 * TanStack Start sama sekali — hanya React + Tailwind + alias path — dan
 * memakai `src/routeTree.gen.ts` yang sudah ada di repo.
 */
import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import tailwindcss from "@tailwindcss/vite";
import tsConfigPaths from "vite-tsconfig-paths";

export default defineConfig({
  plugins: [react(), tailwindcss(), tsConfigPaths()],
  build: {
    outDir: "dist-spa",
    emptyOutDir: true,
    sourcemap: false,
  },
});
