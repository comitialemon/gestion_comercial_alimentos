import { defineConfig } from "vite"
import laravel from "laravel-vite-plugin"
import vue from "@vitejs/plugin-vue"
import path from "path"

export default defineConfig({
  plugins: [
    laravel({
      input: ["resources/js/app.js"],
      refresh: true,
    }),
    vue(),
  ],
  server: {
    host: true,
    port: 5175,           // 🔥 CAMBIADO: 5173 → 5175
    strictPort: true,
    hmr: {
      host: "localhost",
      protocol: "ws",
      port: 5175,         // 🔥 CAMBIADO: 5174 → 5175
    },
    watch: {
      usePolling: true,
    },
  },
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "resources/js"),
    },
  },
})