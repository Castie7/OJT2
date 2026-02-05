import { ref, reactive, onMounted } from 'vue'
import { API_BASE_URL } from '../apiConfig'

export interface User {
  id: number
  name: string
  email: string
  // ✅ FIX 1: Update type definition to match database
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
    // ✅ FIX 2: Change default from 'student' to 'user'
    role: 'user' as 'admin' | 'user' 
  })

  // --- HELPERS ---
  const getHeaders = () => {
    const token = document.cookie.split('; ').find(row => row.startsWith('auth_token='))?.split('=')[1]
    return { 'Authorization': token || '' }
  }

  // --- ACTIONS ---

  // 1. Fetch All Users
  const fetchUsers = async () => {
    isLoading.value = true
    try {
      const response = await fetch(`${API_BASE_URL}/admin/users`, { headers: getHeaders() })
      if (response.ok) {
        users.value = await response.json()
      }
    } catch (error) {
      console.error("Fetch error:", error)
    } finally {
      isLoading.value = false
    }
  }

  // 2. Add New User
  const addUser = async () => {
    if (!form.name || !form.email || !form.password) {
      alert("⚠️ Please fill in all required fields.")
      return
    }

    isSubmitting.value = true
    try {
      const response = await fetch(`${API_BASE_URL}/auth/register`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', ...getHeaders() },
        body: JSON.stringify(form)
      })

      const result = await response.json()

      if (response.ok) {
        alert("✅ User added successfully!")
        
        // Reset Form
        form.name = ''
        form.email = ''
        form.password = ''
        form.role = 'user' // ✅ FIX 3: Reset to 'user' (not student)
        
        showAddForm.value = false
        fetchUsers() // Refresh list
      } else {
        alert("❌ Error: " + (result.message || "Failed to create user"))
      }
    } catch (error) {
      // 💡 Tip: This alerts you if the error persists
      console.error(error)
      alert("❌ Server Error: Likely due to Database/Role mismatch. Check console logs.") 
    } finally {
      isSubmitting.value = false
    }
  }

  // 3. Reset Password
  const resetPassword = async (userId: number, userName: string) => {
    const newPass = prompt(`Enter new password for ${userName}:`)
    if (!newPass) return 
    
    if (newPass.length < 6) {
        alert("⚠️ Password must be at least 6 characters.")
        return
    }

    try {
      const response = await fetch(`${API_BASE_URL}/admin/reset-password`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', ...getHeaders() },
        body: JSON.stringify({ user_id: userId, new_password: newPass })
      })

      if (response.ok) {
        alert(`✅ Password for ${userName} has been reset.`)
      } else {
        alert("❌ Failed to reset password.")
      }
    } catch (error) {
      alert("❌ Server Connection Error")
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