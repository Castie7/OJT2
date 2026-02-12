import { ref, reactive, onMounted } from 'vue'
import api from '../services/api' // ✅ Switch to Secure central API
import { useToast } from './useToast'

export interface User {
  id: number
  name: string
  email: string
  role: 'admin' | 'user'
  created_at: string
}

export function useUserManagement() {

  // --- STATE ---
  const users = ref<User[]>([])
  const isLoading = ref(false)
  const isSubmitting = ref(false)
  const showAddForm = ref(false)

  // Form Data
  const form = reactive({
    name: '',
    email: '',
    password: '',
    role: 'user' as 'admin' | 'user'
  })
  const { showToast } = useToast()

  // --- ACTIONS ---

  // 1. Fetch All Users
  const fetchUsers = async () => {
    isLoading.value = true
    try {
      // ✅ Axios automatically handles baseURL and auth cookies
      const response = await api.get('/admin/users')
      users.value = response.data
    } catch (error) {
      console.error("Fetch error:", error)
    } finally {
      isLoading.value = false
    }
  }

  // 2. Add New User
  const addUser = async () => {
    if (!form.name || !form.email || !form.password) {
      showToast("⚠️ Please fill in all required fields.", "warning")
      return
    }

    isSubmitting.value = true
    try {
      // ✅ Uses central API POST with CSRF protection
      const response = await api.post('/auth/register', {
        name: form.name,
        email: form.email,
        password: form.password,
        role: form.role
      })

      if (response.data.status === 'success' || response.status === 200) {
        showToast("✅ User added successfully!", "success")

        // Reset Form
        form.name = ''
        form.email = ''
        form.password = ''
        form.role = 'user'

        showAddForm.value = false
        fetchUsers() // Refresh list
      }
    } catch (error: any) {
      console.error(error)
      const msg = error.response?.data?.message || "Failed to create user"
      showToast("❌ Error: " + msg, "error")
    } finally {
      isSubmitting.value = false
    }
  }

  // 3. Reset Password
  const resetPassword = async (userId: number, userName: string) => {
    const newPass = prompt(`Enter new password for ${userName}:`)
    if (!newPass) return

    if (newPass.length < 6) {
      showToast("⚠️ Password must be at least 6 characters.", "warning")
      return
    }

    try {
      // ✅ Endpoints updated to use the secure axios instance
      const response = await api.post('/admin/reset-password', {
        user_id: userId,
        new_password: newPass
      })

      if (response.status === 200) {
        showToast(`✅ Password for ${userName} has been reset.`, "success")
      }
    } catch (error: any) {
      console.error(error)
      showToast("❌ Failed to reset password. Check admin permissions.", "error")
    }
  }

  // Load data on mount
  onMounted(() => {
    fetchUsers()
  })

  return {
    users,
    isLoading,
    isSubmitting,
    showAddForm,
    form,
    fetchUsers,
    addUser,
    resetPassword
  }
}