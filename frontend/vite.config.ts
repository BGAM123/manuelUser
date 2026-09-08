import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import tailwindcss from "@tailwindcss/vite";
import tsconfigPaths from "vite-tsconfig-paths";

export default defineConfig({
  plugins: [react(), tailwindcss(), tsconfigPaths()],
  server: {
    host: "::",
    port: 8080,
    strictPort: true,
    proxy: {
      // Redirige /backend-api/* → http://185.98.136.192:8075/*
      // (évite les problèmes CORS en dev ; /api est réservé par l'environnement
      // de prévisualisation et renvoyait une erreur 500)
      "/backend-api": {
        // target: "http://127.0.0.1:8000",
        target: "http://185.98.136.192:8075",
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/backend-api/, ""),
      },
      // Fichiers uploadés (photos, pièces jointes) — servis directement par le
      // backend hors du préfixe /api, donc besoin de sa propre règle de proxy.
      "/uploads": {
        // target: "http://127.0.0.1:8000",
        target: "http://185.98.136.192:8075",
        changeOrigin: true,
      },
    },
  },
  preview: {
    host: "::",
    port: 8080,
    strictPort: true,
  },
  build: {
    outDir: "dist",
    emptyOutDir: true,
    rollupOptions: {
      output: {
        manualChunks(id) {
          if (!id.includes("node_modules")) return undefined;
          if (id.includes("jspdf") || id.includes("jspdf-autotable")) return "pdf";
          if (id.includes("xlsx")) return "xlsx";
          if (id.includes("leaflet") || id.includes("react-leaflet")) return "maps";
          if (id.includes("react-dom") || id.includes("react-router") || /node_modules[\\/]react[\\/]/.test(id)) return "react";
          if (id.includes("@tanstack")) return "tanstack";
          if (id.includes("@radix-ui")) return "radix";
          return "vendor";
        },
      },
    },
  },
});
