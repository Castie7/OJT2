<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from './services/api' 
import { RouterView, useRouter } from 'vue-router'

interface User {
  id: number; 
  name: string; 
  role: string; 
  email: string;
}

const currentUser = ref<User | null>(null)
const isLoading = ref(true) 
const router = useRouter()

// --- Token Management ---
const saveToken = (token: string) => {
    if (!token) return;
    // Save to cookie for backend compatibility
    document.cookie = `csrf_cookie_name=${token}; path=/; domain=${window.location.hostname}; secure; samesite=None`;
    // Save to LocalStorage as a "Bridge" for our Axios interceptor
    localStorage.setItem('csrf_token_backup', token);
}

onMounted(async () => {
  try {
    const response = await api.get('/auth/verify')
    
    // Refresh security token if provided
    if (response.data.csrf_token) {
       saveToken(response.data.csrf_token);
    }
    
    if (response.data.status === 'success') {
      currentUser.value = response.data.user
      
      // If already logged in, don't stay on the login page
      if (window.location.pathname === '/login') {
        router.push('/') // Redirect to Dashboard (Home)
      }
    } else {
      currentUser.value = null
    }
  } catch (error) {
    console.error("Session verification failed:", error)
    currentUser.value = null
  } finally {
    isLoading.value = false
  }
})

// --- Event Handlers ---

const onLoginSuccess = (data: any) => {
  if (data.csrf_token) saveToken(data.csrf_token);
  currentUser.value = data.user
  router.push('/') // Move from Login form to Dashboard
}

const handleLogout = async () => {
  try {
    await api.post('/auth/logout')
    localStorage.removeItem('csrf_token_backup');
    delete api.defaults.headers.common['X-CSRF-TOKEN'];
  } catch (e) {
    console.warn("Logout request failed, cleaning local state anyway.")
  } finally {
    currentUser.value = null
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