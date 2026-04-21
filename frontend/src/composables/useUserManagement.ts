import { ref, reactive, computed, onMounted } from 'vue'
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

  // --- RESET PASSWORD MODAL STATE ---
  const showResetModal = ref(false)
  const resetTarget = reactive({ id: 0, name: '' })
  const resetForm = reactive({ password: '' })
  const isResetting = ref(false)
  const resetError = ref('')
  const showResetPass = ref(false)

  const resetPassRules = computed(() => ({
    minLength: resetForm.password.length >= 10,
    hasUpper: /[A-Z]/.test(resetForm.password),
    hasLower: /[a-z]/.test(resetForm.password),
    hasNumber: /\d/.test(resetForm.password),
    hasSpecial: /[^a-zA-Z0-9]/.test(resetForm.password),
  }))

  const allResetRulesPassed = computed(() =>
    resetPassRules.value.minLength && resetPassRules.value.hasUpper &&
    resetPassRules.value.hasLower && resetPassRules.value.hasNumber &&
    resetPassRules.value.hasSpecial
  )

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
      showToast("Please fill in all required fields.", "warning")
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
        showToast("User added successfully!", "success")

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
      showToast("Error: " + msg, "error")
    } finally {
      isSubmitting.value = false
    }
  }

  // 3. Open Reset Password Modal
  const openResetModal = (userId: number, userName: string) => {
    resetTarget.id = userId
    resetTarget.name = userName
    resetForm.password = ''
    resetError.value = ''
    showResetPass.value = false
    showResetModal.value = true
  }

  const closeResetModal = () => {
    showResetModal.value = false
    resetForm.password = ''
    resetError.value = ''
  }

  // 4. Submit Reset Password
  const submitResetPassword = async () => {
    resetError.value = ''
    if (!allResetRulesPassed.value) {
      resetError.value = 'Please meet all password requirements.'
      return
    }

    isResetting.value = true
    try {
      const response = await adminService.resetPassword({
        user_id: resetTarget.id,
        new_password: resetForm.password
      })

      if (response.status === 'success') {
        showToast(`Password for ${resetTarget.name} has been reset. They will be required to change it on next login.`, "success")
        closeResetModal()
      }
    } catch (error: any) {
      console.error(error)
      resetError.value = error.response?.data?.messages?.error || error.response?.data?.message || "Failed to reset password."
    } finally {
      isResetting.value = false
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
    // Reset modal
    showResetModal,
    resetTarget,
    resetForm,
    isResetting,
    resetError,
    showResetPass,
    resetPassRules,
    allResetRulesPassed,
    openResetModal,
    closeResetModal,
    submitResetPassword
  }
}