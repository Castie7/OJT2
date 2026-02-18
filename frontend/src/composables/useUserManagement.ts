import { ref, reactive, onMounted } from 'vue'
import { adminService, authService } from '../services'
import { useToast } from './useToast'
import type { User } from '../types'

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
      users.value = await adminService.getUsers()
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
      const response = await authService.register({
        name: form.name,
        email: form.email,
        password: form.password,
        role: form.role
      })

      if (response.status === 'success') {
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
    const newPass = prompt(`Enter new password for ${userName}: `)
    if (!newPass) return

    if (newPass.length < 6) {
      showToast("⚠️ Password must be at least 6 characters.", "warning")
      return
    }

    try {
      const response = await adminService.resetPassword({
        user_id: userId,
        new_password: newPass
      })

      if (response.status === 'success') {
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