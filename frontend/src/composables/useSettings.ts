import { ref, reactive, watch, type Ref } from 'vue'
import api from '../services/api' // ✅ Switch to Secure API Service

export interface User {
  id: number
  name: string
  email: string
  role: string
}

// 1. Add 'triggerLogout' to arguments
export function useSettings(
  currentUserRef: Ref<User | null>, 
  updateSessionUser: (u: User) => void,
  triggerLogout: () => void 
) {
  
  const isProfileLoading = ref(false)
  const isPasswordLoading = ref(false)
  
  const showCurrentPass = ref(false)
  const showNewPass = ref(false)

  const profileForm = reactive({
    name: currentUserRef.value?.name || '',
    email: currentUserRef.value?.email || ''
  })

  const passForm = reactive({
    current: '',
    new: '',
    confirm: ''
  })

  watch(currentUserRef, (newUser) => {
    if (newUser) {
      profileForm.name = newUser.name
      profileForm.email = newUser.email 
    }
  }, { immediate: true, deep: true })

  // --- ACTION 1: SAVE PROFILE -> REFRESH PAGE ---
  const saveProfile = async () => {
    const user = currentUserRef.value
    if (!user) return
    
    isProfileLoading.value = true

    try {
      // ✅ Use api.post()
      // Automatically handles Base URL, CSRF Token, and Cookies
      const response = await api.post('/auth/update-profile', {
          user_id: user.id,
          name: profileForm.name,
          email: profileForm.email
      })

      if (response.data.status === 'success') {
        alert("✅ Profile updated successfully! The page will now refresh.")
        // FORCE PAGE RELOAD to reflect changes in session
        window.location.reload()
      } else {
        alert("❌ " + (response.data.message || "Failed"))
      }

    } catch (error: any) {
       console.error(error)
       const msg = error.response?.data?.message || "Server Error"
       alert("❌ " + msg)
    } finally { 
       isProfileLoading.value = false 
    }
  }

  // --- ACTION 2: CHANGE PASSWORD -> LOGOUT ---
  const changePassword = async () => {
    const user = currentUserRef.value
    if (!user) return
    
    if (!passForm.current) { alert("⚠️ Enter current password"); return }
    if (passForm.new.length < 6) { alert("⚠️ Password must be 6+ chars"); return }
    if (passForm.new !== passForm.confirm) { alert("⚠️ Passwords do not match"); return }

    isPasswordLoading.value = true

    try {
      // ✅ Use api.post()
      const response = await api.post('/auth/update-profile', {
          user_id: user.id,
          current_password: passForm.current,
          new_password: passForm.new
      })

      if (response.data.status === 'success') {
        alert("✅ Password changed successfully! Please login again.")
        // TRIGGER LOGOUT
        triggerLogout()
      } else {
        alert("❌ " + (response.data.message || "Failed"))
      }

    } catch (error: any) {
       console.error(error)
       const msg = error.response?.data?.message || "Server Error"
       alert("❌ " + msg)
    } finally { 
       isPasswordLoading.value = false 
    }
  }

  return { 
    profileForm, passForm, 
    isProfileLoading, isPasswordLoading,
    saveProfile, changePassword,
    showCurrentPass, showNewPass
  }
}