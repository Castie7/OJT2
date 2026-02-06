import { ref } from 'vue'
import api from '../services/api' // ✅ Secure API Service

export function useLoginForm(emit: {
  (e: 'login-success', data: any): void;
  (e: 'back'): void;
}) {
  
  // --- STATE ---
  const email = ref('')
  const password = ref('')
  const message = ref('')
  const isSuccess = ref(false)
  const isLoading = ref(false)

  // --- ACTIONS ---
  const handleLogin = async () => {
    isLoading.value = true
    message.value = ""
    
    try {
      // ✅ SECURE POST REQUEST
      // The 'api' interceptor automatically adds:
      // 1. The X-CSRF-TOKEN header (from Cookie or LocalStorage)
      // 2. The 'withCredentials' flag
      const response = await api.post('/auth/login', { 
        email: email.value, 
        password: password.value 
      })

      const data = response.data
      
      if (data.status === 'success') {
        isSuccess.value = true
        message.value = "Login Successful!"
        
        // Pass the data (including the new CSRF token) up to App.vue
        // App.vue will handle saving it to the "Bridge" (LocalStorage)
        setTimeout(() => {
          emit('login-success', data)
        }, 1000)

      } else {
        isSuccess.value = false
        message.value = data.message || "Invalid credentials"
        isLoading.value = false
      }

    } catch (error: any) {
      console.error('Login error:', error)
      isSuccess.value = false
      isLoading.value = false

      if (error.response) {
        // 🛡️ SPECIFIC ERROR HANDLING
        if (error.response.status === 403) {
             // 403 usually means the CSRF token is missing or expired
             message.value = "Session expired. Please refresh the page."
        } else {
             message.value = error.response.data.message || "Invalid credentials"
        }
      } else if (error.request) {
        // Server is down or unreachable
        message.value = "Server connection failed. Please try again."
      } else {
        message.value = "An unexpected error occurred."
      }
    }
  }

  return {
    email,
    password,
    message,
    isSuccess,
    isLoading,
    handleLogin
  }
}