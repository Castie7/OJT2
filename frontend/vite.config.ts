import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue()],
  server: {
    host: true,        // 1. Exposes the server to your Wi-Fi network (0.0.0.0)
    port: 5173,        // 2. Ensures the port stays consistent
    strictPort: true,  // 3. Prevents Vite from switching ports if 5173 is busy
  },
})