import { ref } from 'vue'

// ---------------------------------------------------------------------------
// ✅ CONFIGURATION: Must match your other files
// ---------------------------------------------------------------------------
const API_BASE_URL = 'http://192.168.60.36/OJT2/backend/public';
// ---------------------------------------------------------------------------

// Change only the type definition here:
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
      // ✅ FIXED: Uses the correct API URL
      const response = await fetch(`${API_BASE_URL}/auth/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
          email: email.value, 
          password: password.value 
        })
      })

      const data = await response.json()
      
      if (data.status === 'success') {
        isSuccess.value = true
        message.value = "Login Successful!"
        
        // Save token to cookie immediately (Optional but good practice)
        if(data.token) {
            document.cookie = `auth_token=${data.token}; path=/; max-age=86400; SameSite=Lax`
        }

        setTimeout(() => {
          // This call remains valid
          emit('login-success', data)
        }, 1000)
      } else {
        isSuccess.value = false
        message.value = data.message || "Invalid credentials"
        isLoading.value = false
      }

    } catch (error) {
      console.error(error)
      message.value = "Server connection failed."
      isLoading.value = false
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