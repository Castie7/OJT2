<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from './services/api' 
import { RouterView, useRouter } from 'vue-router'
import Toast from './components/shared/Toast.vue'
import { useAuth } from './composables/useAuth'


const { currentUser, setUser, clearUser, isInitialized, setInitialized } = useAuth()
const isLoading = ref(!isInitialized.value) 
const router = useRouter()

/* Watch for initialization from router guard */
import { watch } from 'vue'
watch(isInitialized, (newVal) => {
    if (newVal) isLoading.value = false
}, { immediate: true })

// --- Token Management ---
const saveToken = (token: string) => {
    if (!token) return;
    // Save to cookie for backend compatibility
    document.cookie = `csrf_cookie_name=${token}; path=/; domain=${window.location.hostname}; secure; samesite=None`;
    // Save to LocalStorage as a "Bridge" for our Axios interceptor
    localStorage.setItem('csrf_token_backup', token);
}

onMounted(async () => {
  // If router guard already handled initialization, just stop loading
  if (isInitialized.value) {
    isLoading.value = false
    return
  }

  // Fallback for cases where router guard didn't run (e.g. 404 page if exists outside routes)
  try {
    const response = await api.get('/auth/verify')
    
    if (response.data.csrf_token) {
       saveToken(response.data.csrf_token);
    }
    
    if (response.data.status === 'success') {
      setUser(response.data.user)
      if (window.location.pathname === '/login') {
        router.push('/')
      }
    } else {
      clearUser()
    }
  } catch (error) {
    console.error("Session verification failed:", error)
    clearUser()
  } finally {
    isLoading.value = false
    setInitialized(true)
  }
})

// --- Event Handlers ---

const onLoginSuccess = (data: any) => {
  if (data.csrf_token) saveToken(data.csrf_token);
  setUser(data.user)
  router.push('/') // Redirect to home after login
}

const handleLogout = async () => {
  try {
    await api.post('/auth/logout')
    localStorage.removeItem('csrf_token_backup');
    delete api.defaults.headers.common['X-CSRF-TOKEN'];
  } catch (e) {
    console.warn("Logout request failed, cleaning local state anyway.")
  } finally {
    clearUser()
    // Force a full page refresh to ensure clean state
    window.location.href = '/login'; 
  }
}

const goToLogin = () => {
  router.push('/login')
}
</script>

<template>
  <div v-if="isLoading" class="min-h-screen flex items-center justify-center bg-gray-50">
    <div class="flex flex-col items-center gap-4">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-green-700"></div>
      <p class="text-green-800 font-medium animate-pulse">Initializing Portal...</p>
    </div>
  </div>

  <template v-else>
    <RouterView 
      :currentUser="currentUser" 
      @login-success="onLoginSuccess"
      @login-click="goToLogin"
      @logout-click="handleLogout"
      @update-user="(u: any) => currentUser = u"
      @back="router.push('/')"
    />
    <Toast />
  </template>
</template>

<style>
/* Reset and global transitions */
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}

body {
  margin: 0;
  background-color: #f9fafb;
}
</style>