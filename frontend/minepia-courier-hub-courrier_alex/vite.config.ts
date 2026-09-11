import { defineConfig } from "vite";
import react from "@vitejs/plugin-react-swc";
import path from "path";
import { componentTagger } from "lovable-tagger";

// https://vitejs.dev/config/
export default defineConfig(({ mode }) => ({
  server: {
    host: "::",
    port: 8080,
  },
  plugins: [react(), mode === "development" && componentTagger()].filter(Boolean),
  optimizeDeps: {
    include: ["jspdf"],
  },
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "./src"),
    },
  },
  // build: {
  //   rollupOptions: {
  //     output: {
  //       manualChunks: {
  //         pdfmake: ["pdfmake/build/pdfmake", "pdfmake/build/vfs_fonts"],
  //         jspdf: ["jspdf"],
  //         html2canvas: ["html2canvas"],
  //         vendor: [
  //           "react",
  //           "react-dom",
  //           "react-router-dom",
  //           "date-fns",
  //         ],
  //       },
  //     },
  //   },
  //   chunkSizeWarningLimit: 3000,
  // },
  build: {
    rollupOptions: {
      output: {
        manualChunks(id) {
          if (id.includes("node_modules")) {
            if (id.includes("pdfmake")) return "pdfmake";
            if (id.includes("jspdf")) return "jspdf";
            if (id.includes("html2canvas")) return "html2canvas";

            return "vendor";
          }
        },
      },
    },
    chunkSizeWarningLimit: 3000,
  },
}));
