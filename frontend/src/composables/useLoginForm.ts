import { ref } from 'vue'

export function useLoginForm(emit: (event: 'login-success' | 'back', payload?: any) => void) {
  
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
      const response = await fetch('http://localhost:8080/auth/login', {
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
        
        // Delay to show success animation before redirecting
        setTimeout(() => {
          emit('login-success', data)
        }, 1000)
      } else {
        isSuccess.value = false
        message.value = data.message || "Invalid credentials"
        isLoading.value = false
      }

    } catch (error) {
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