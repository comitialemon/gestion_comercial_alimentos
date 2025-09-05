import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import path from 'path' // ⬅️ IMPORTANTE

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/js/app.js'],
      refresh: true,
    }),
    vue({
      template: {
        transformAssetUrls: { base: null, includeAbsolute: false },
      },
    }),
  ],
  server: {
    host: true,                           // escucha en 0.0.0.0 (Docker/WSL)
    port: Number(process.env.VITE_DEV_SERVER_PORT) || 5173,
    strictPort: true,
    hmr: {
      host: process.env.VITE_HMR_HOST || 'localhost', // para hot reload
      protocol: 'ws',
      port: Number(process.env.VITE_DEV_SERVER_PORT) || 5173,
    },
    watch: {
      usePolling: true,                   // asegura que detecte cambios en WSL/Docker
    },
  },
  resolve: {
    alias: {
      // ⬇️ Esto le dice a Vite dónde encontrar "ziggy"
      ziggy: path.resolve('vendor/tightenco/ziggy/dist'),
      '@': '/resources/js',
    },
  },
})
