import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { User } from '../types'
import api from '../services/api'
import { apiCache } from '../utils/apiCache'

export const useAuthStore = defineStore('auth', () => {
  // State
  const currentUser = ref<User | null>(null)
  const isInitialized = ref(false)

  // Getters (Computed)
  const isAuthenticated = computed(() => currentUser.value !== null)
  const userRole = computed(() => currentUser.value?.role || null)
  const userName = computed(() => currentUser.value?.name || '')

  // Actions
  const setUser = (user: User | null) => {
    currentUser.value = user
    apiCache.invalidate('research:')
  }

  const clearUser = () => {
    currentUser.value = null
    apiCache.invalidate('research:')
  }

  const hasRole = (role: string | string[]): boolean => {
    if (!currentUser.value) return false

    if (Array.isArray(role)) {
      return role.includes(currentUser.value.role)
    }

    return currentUser.value.role === role
  }

  // Token Management (Moved from App.vue)
  const saveToken = (token: string) => {
    if (!token) return
    document.cookie = `csrf_cookie_name=${token}; path=/; domain=${window.location.hostname}; secure; samesite=Lax`
    sessionStorage.setItem('csrf_token_backup', token)
  }

  // Initialization Action (Replaces App.vue onMounted logic)
  const init = async () => {
    if (isInitialized.value) return

    try {
      // In a real app, you might check if we have a token first, but since it's HttpOnly cookie + CSRF,
      // we just try to hit the verify endpoint.
      const response = await api.get('/auth/verify')

      if (response.data.csrf_token) {
        saveToken(response.data.csrf_token)
      }

      if (response.data.status === 'success') {
        setUser(response.data.user)
      } else {
        clearUser()
      }
    } catch (error) {
      console.error("Session verification failed:", error)
      clearUser()
    } finally {
      isInitialized.value = true
    }
  }

  const logout = async () => {
    try {
      await api.post('/auth/logout')
      sessionStorage.removeItem('csrf_token_backup')
      delete api.defaults.headers.common['X-CSRF-TOKEN']
    } catch (e) {
      console.warn("Logout request failed, cleaning local state anyway.")
    } finally {
      clearUser()
      // We might want to handle redirect here or let the component do it
    }
  }

  return {
    currentUser,
    isInitialized,
    isAuthenticated,
    userRole,
    userName,
    setUser,
    clearUser,
    hasRole,
    init,
    logout,
    saveToken
  }
})
