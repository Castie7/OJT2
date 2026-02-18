import { ref, onMounted, onUnmounted } from 'vue'
import { authService } from '../services'

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
  const isLockedOut = ref(false)
  const lockoutSeconds = ref(0)

  let countdownTimer: ReturnType<typeof setInterval> | null = null

  // --- LOCKOUT COUNTDOWN ---
  const startLockoutCountdown = (seconds: number) => {
    // Clear any existing timer
    if (countdownTimer) clearInterval(countdownTimer)

    isLockedOut.value = true
    lockoutSeconds.value = seconds
    message.value = `Too many failed attempts. Please try again in ${seconds} second(s).`

    countdownTimer = setInterval(() => {
      lockoutSeconds.value--
      if (lockoutSeconds.value <= 0) {
        // Lockout expired
        isLockedOut.value = false
        lockoutSeconds.value = 0
        message.value = ''
        if (countdownTimer) {
          clearInterval(countdownTimer)
          countdownTimer = null
        }
      } else {
        message.value = `Too many failed attempts. Please try again in ${lockoutSeconds.value} second(s).`
      }
    }, 1000)
  }

  // Clean up timer on unmount
  onUnmounted(() => {
    if (countdownTimer) clearInterval(countdownTimer)
  })

  // --- ACTIONS ---
  const fetchCsrfToken = async () => {
    try {
      await authService.verify()
    } catch (e) {
      console.warn("Failed to init CSRF token", e)
    }
  }

  // Ensure we have a token when the form mounts
  onMounted(() => {
    fetchCsrfToken()
  })

  const handleLogin = async () => {
    if (isLockedOut.value) return

    isLoading.value = true
    message.value = ""

    try {
      const data = await authService.login({
        email: email.value,
        password: password.value
      })

      if (data.status === 'success') {
        isSuccess.value = true
        message.value = "Login Successful!"

        // Pass the data (including the new CSRF token) up to App.vue
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
        if (error.response.status === 429) {
          // 🔒 Too many attempts – start countdown
          const retryAfter = error.response.data?.retry_after || 60
          startLockoutCountdown(retryAfter)
        } else if (error.response.status === 403) {
          message.value = "Session expired. Please refresh the page."
        } else {
          message.value = error.response.data.message || "Invalid credentials"
        }
      } else if (error.request) {
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
    isLockedOut,
    lockoutSeconds,
    handleLogin
  }
}
