import { ref, reactive, watch, type Ref } from 'vue'

export interface User {
  id: number
  name: string
  email: string
  role: string
}

// ---------------------------------------------------------------------------
// ✅ CONFIGURATION: Must match your other files
// ---------------------------------------------------------------------------
const API_BASE_URL = 'http://192.168.60.36/OJT2/backend/public';
// ---------------------------------------------------------------------------

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
      // ✅ FIXED: Uses API_BASE_URL
      const response = await fetch(`${API_BASE_URL}/auth/update-profile`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          user_id: user.id,
          name: profileForm.name,
          email: profileForm.email
        })
      })

      const res = await response.json()
      
      if (response.ok) {
        alert("✅ Profile updated successfully! The page will now refresh.")
        // FORCE PAGE RELOAD
        window.location.reload()
      } else {
        alert("❌ " + (res.message || "Failed"))
      }
    } catch (e) { alert("Server Error") } 
    finally { isProfileLoading.value = false }
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
      // ✅ FIXED: Uses API_BASE_URL
      const response = await fetch(`${API_BASE_URL}/auth/update-profile`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          user_id: user.id,
          current_password: passForm.current,
          new_password: passForm.new
        })
      })

      const res = await response.json()
      
      if (response.ok) {
        alert("✅ Password changed successfully! Please login again.")
        // TRIGGER LOGOUT
        triggerLogout()
      } else {
        alert("❌ " + (res.message || "Failed"))
      }
    } catch (e) { alert("Server Error") } 
    finally { isPasswordLoading.value = false }
  }

  return { 
    profileForm, passForm, 
    isProfileLoading, isPasswordLoading,
    saveProfile, changePassword,
    showCurrentPass, showNewPass
  }
}