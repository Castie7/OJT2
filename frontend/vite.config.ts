import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import basicSsl from '@vitejs/plugin-basic-ssl' // ✅ 1. Import the plugin

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    basicSsl() // ✅ 2. Add it to the plugins list
  ],
  server: {
    host: true,        // Exposes to Network (0.0.0.0)
    port: 5173,        // Keeps port consistent
    strictPort: true,  // Prevents port switching
    // https: true     // (Optional) The plugin handles this automatically now
  },
})