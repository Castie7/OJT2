import { ref, computed, type Ref } from 'vue'
import type { User } from '../types'

/**
 * Authentication state management composable
 * Provides centralized access to current user and authentication status
 */

// Global authentication state (shared across the app)
const currentUser = ref<User | null>(null)
const isInitialized = ref(false)

export function useAuth() {
  // Computed properties
  const isAuthenticated = computed(() => currentUser.value !== null)
  const userRole = computed(() => currentUser.value?.role || null)
  const userName = computed(() => currentUser.value?.name || '')

  // Methods
  const setUser = (user: User | null) => {
    currentUser.value = user
  }

  const clearUser = () => {
    currentUser.value = null
  }

  const hasRole = (role: string | string[]) => {
    if (!currentUser.value) return false

    if (Array.isArray(role)) {
      return role.includes(currentUser.value.role)
    }

    return currentUser.value.role === role
  }

  // Expose the ref for reactive access
  const getCurrentUser = (): Ref<User | null> => {
    return currentUser
  }

  return {
    // State
    currentUser: getCurrentUser(),
    isInitialized,

    // Computed
    isAuthenticated,
    userRole,
    userName,

    // Methods
    setUser,
    clearUser,
    hasRole,
    setInitialized: (value: boolean) => isInitialized.value = value
  }
}
