// vite.config.ts
import { defineConfig } from "file:///E:/MINEPIA/MINEPIA/minepia-courier-hub/node_modules/vite/dist/node/index.js";
import react from "file:///E:/MINEPIA/MINEPIA/minepia-courier-hub/node_modules/@vitejs/plugin-react-swc/index.js";
import path from "path";
import { componentTagger } from "file:///E:/MINEPIA/MINEPIA/minepia-courier-hub/node_modules/lovable-tagger/dist/index.js";
var __vite_injected_original_dirname = "E:\\MINEPIA\\MINEPIA\\minepia-courier-hub";
var vite_config_default = defineConfig(({ mode }) => ({
  server: {
    host: "::",
    port: 8080
  },
  plugins: [react(), mode === "development" && componentTagger()].filter(Boolean),
  optimizeDeps: {
    include: ["jspdf"]
  },
  resolve: {
    alias: {
      "@": path.resolve(__vite_injected_original_dirname, "./src")
    }
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
        }
      }
    },
    chunkSizeWarningLimit: 3e3
  }
}));
export {
  vite_config_default as default
};
//# sourceMappingURL=data:application/json;base64,ewogICJ2ZXJzaW9uIjogMywKICAic291cmNlcyI6IFsidml0ZS5jb25maWcudHMiXSwKICAic291cmNlc0NvbnRlbnQiOiBbImNvbnN0IF9fdml0ZV9pbmplY3RlZF9vcmlnaW5hbF9kaXJuYW1lID0gXCJFOlxcXFxNSU5FUElBXFxcXE1JTkVQSUFcXFxcbWluZXBpYS1jb3VyaWVyLWh1YlwiO2NvbnN0IF9fdml0ZV9pbmplY3RlZF9vcmlnaW5hbF9maWxlbmFtZSA9IFwiRTpcXFxcTUlORVBJQVxcXFxNSU5FUElBXFxcXG1pbmVwaWEtY291cmllci1odWJcXFxcdml0ZS5jb25maWcudHNcIjtjb25zdCBfX3ZpdGVfaW5qZWN0ZWRfb3JpZ2luYWxfaW1wb3J0X21ldGFfdXJsID0gXCJmaWxlOi8vL0U6L01JTkVQSUEvTUlORVBJQS9taW5lcGlhLWNvdXJpZXItaHViL3ZpdGUuY29uZmlnLnRzXCI7aW1wb3J0IHsgZGVmaW5lQ29uZmlnIH0gZnJvbSBcInZpdGVcIjtcclxuaW1wb3J0IHJlYWN0IGZyb20gXCJAdml0ZWpzL3BsdWdpbi1yZWFjdC1zd2NcIjtcclxuaW1wb3J0IHBhdGggZnJvbSBcInBhdGhcIjtcclxuaW1wb3J0IHsgY29tcG9uZW50VGFnZ2VyIH0gZnJvbSBcImxvdmFibGUtdGFnZ2VyXCI7XHJcblxyXG4vLyBodHRwczovL3ZpdGVqcy5kZXYvY29uZmlnL1xyXG5leHBvcnQgZGVmYXVsdCBkZWZpbmVDb25maWcoKHsgbW9kZSB9KSA9PiAoe1xyXG4gIHNlcnZlcjoge1xyXG4gICAgaG9zdDogXCI6OlwiLFxyXG4gICAgcG9ydDogODA4MCxcclxuICB9LFxyXG4gIHBsdWdpbnM6IFtyZWFjdCgpLCBtb2RlID09PSBcImRldmVsb3BtZW50XCIgJiYgY29tcG9uZW50VGFnZ2VyKCldLmZpbHRlcihCb29sZWFuKSxcclxuICBvcHRpbWl6ZURlcHM6IHtcclxuICAgIGluY2x1ZGU6IFtcImpzcGRmXCJdLFxyXG4gIH0sXHJcbiAgcmVzb2x2ZToge1xyXG4gICAgYWxpYXM6IHtcclxuICAgICAgXCJAXCI6IHBhdGgucmVzb2x2ZShfX2Rpcm5hbWUsIFwiLi9zcmNcIiksXHJcbiAgICB9LFxyXG4gIH0sXHJcbiAgLy8gYnVpbGQ6IHtcclxuICAvLyAgIHJvbGx1cE9wdGlvbnM6IHtcclxuICAvLyAgICAgb3V0cHV0OiB7XHJcbiAgLy8gICAgICAgbWFudWFsQ2h1bmtzOiB7XHJcbiAgLy8gICAgICAgICBwZGZtYWtlOiBbXCJwZGZtYWtlL2J1aWxkL3BkZm1ha2VcIiwgXCJwZGZtYWtlL2J1aWxkL3Zmc19mb250c1wiXSxcclxuICAvLyAgICAgICAgIGpzcGRmOiBbXCJqc3BkZlwiXSxcclxuICAvLyAgICAgICAgIGh0bWwyY2FudmFzOiBbXCJodG1sMmNhbnZhc1wiXSxcclxuICAvLyAgICAgICAgIHZlbmRvcjogW1xyXG4gIC8vICAgICAgICAgICBcInJlYWN0XCIsXHJcbiAgLy8gICAgICAgICAgIFwicmVhY3QtZG9tXCIsXHJcbiAgLy8gICAgICAgICAgIFwicmVhY3Qtcm91dGVyLWRvbVwiLFxyXG4gIC8vICAgICAgICAgICBcImRhdGUtZm5zXCIsXHJcbiAgLy8gICAgICAgICBdLFxyXG4gIC8vICAgICAgIH0sXHJcbiAgLy8gICAgIH0sXHJcbiAgLy8gICB9LFxyXG4gIC8vICAgY2h1bmtTaXplV2FybmluZ0xpbWl0OiAzMDAwLFxyXG4gIC8vIH0sXHJcbiAgYnVpbGQ6IHtcclxuICAgIHJvbGx1cE9wdGlvbnM6IHtcclxuICAgICAgb3V0cHV0OiB7XHJcbiAgICAgICAgbWFudWFsQ2h1bmtzKGlkKSB7XHJcbiAgICAgICAgICBpZiAoaWQuaW5jbHVkZXMoXCJub2RlX21vZHVsZXNcIikpIHtcclxuICAgICAgICAgICAgaWYgKGlkLmluY2x1ZGVzKFwicGRmbWFrZVwiKSkgcmV0dXJuIFwicGRmbWFrZVwiO1xyXG4gICAgICAgICAgICBpZiAoaWQuaW5jbHVkZXMoXCJqc3BkZlwiKSkgcmV0dXJuIFwianNwZGZcIjtcclxuICAgICAgICAgICAgaWYgKGlkLmluY2x1ZGVzKFwiaHRtbDJjYW52YXNcIikpIHJldHVybiBcImh0bWwyY2FudmFzXCI7XHJcblxyXG4gICAgICAgICAgICByZXR1cm4gXCJ2ZW5kb3JcIjtcclxuICAgICAgICAgIH1cclxuICAgICAgICB9LFxyXG4gICAgICB9LFxyXG4gICAgfSxcclxuICAgIGNodW5rU2l6ZVdhcm5pbmdMaW1pdDogMzAwMCxcclxuICB9LFxyXG59KSk7XHJcbiJdLAogICJtYXBwaW5ncyI6ICI7QUFBNFMsU0FBUyxvQkFBb0I7QUFDelUsT0FBTyxXQUFXO0FBQ2xCLE9BQU8sVUFBVTtBQUNqQixTQUFTLHVCQUF1QjtBQUhoQyxJQUFNLG1DQUFtQztBQU16QyxJQUFPLHNCQUFRLGFBQWEsQ0FBQyxFQUFFLEtBQUssT0FBTztBQUFBLEVBQ3pDLFFBQVE7QUFBQSxJQUNOLE1BQU07QUFBQSxJQUNOLE1BQU07QUFBQSxFQUNSO0FBQUEsRUFDQSxTQUFTLENBQUMsTUFBTSxHQUFHLFNBQVMsaUJBQWlCLGdCQUFnQixDQUFDLEVBQUUsT0FBTyxPQUFPO0FBQUEsRUFDOUUsY0FBYztBQUFBLElBQ1osU0FBUyxDQUFDLE9BQU87QUFBQSxFQUNuQjtBQUFBLEVBQ0EsU0FBUztBQUFBLElBQ1AsT0FBTztBQUFBLE1BQ0wsS0FBSyxLQUFLLFFBQVEsa0NBQVcsT0FBTztBQUFBLElBQ3RDO0FBQUEsRUFDRjtBQUFBO0FBQUE7QUFBQTtBQUFBO0FBQUE7QUFBQTtBQUFBO0FBQUE7QUFBQTtBQUFBO0FBQUE7QUFBQTtBQUFBO0FBQUE7QUFBQTtBQUFBO0FBQUE7QUFBQTtBQUFBLEVBbUJBLE9BQU87QUFBQSxJQUNMLGVBQWU7QUFBQSxNQUNiLFFBQVE7QUFBQSxRQUNOLGFBQWEsSUFBSTtBQUNmLGNBQUksR0FBRyxTQUFTLGNBQWMsR0FBRztBQUMvQixnQkFBSSxHQUFHLFNBQVMsU0FBUyxFQUFHLFFBQU87QUFDbkMsZ0JBQUksR0FBRyxTQUFTLE9BQU8sRUFBRyxRQUFPO0FBQ2pDLGdCQUFJLEdBQUcsU0FBUyxhQUFhLEVBQUcsUUFBTztBQUV2QyxtQkFBTztBQUFBLFVBQ1Q7QUFBQSxRQUNGO0FBQUEsTUFDRjtBQUFBLElBQ0Y7QUFBQSxJQUNBLHVCQUF1QjtBQUFBLEVBQ3pCO0FBQ0YsRUFBRTsiLAogICJuYW1lcyI6IFtdCn0K
