import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
// import fs from 'fs' // ✅ Import Node's file system module

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    // basicSsl() ❌ REMOVED: We use your custom mkcert files instead
  ],
  server: {
    host: true,        // ✅ Exposes to Network (0.0.0.0) so phone can connect
    port: 5173,        // Keeps port consistent
    strictPort: true,  // Prevents port switching if 5173 is busy

    // ✅ USE YOUR TRUSTED CERTIFICATES
    // ✅ USE YOUR TRUSTED CERTIFICATES
    // https: {
    //   key: fs.readFileSync('./localhost-key.pem'),
    //   cert: fs.readFileSync('./localhost.pem'),
    // },

    // Optional: Proxy API requests to backend to avoid CORS issues locally
    proxy: {
      '/api': {
        target: 'http://localhost', // or your backend URL
        secure: false, // Accept backend's self-signed certs
        changeOrigin: true
      }
    }
  },
})