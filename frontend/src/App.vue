<script setup lang="ts">
import { ref, watch } from 'vue'
import { RouterView, useRouter } from 'vue-router'
import Toast from './components/shared/Toast.vue'
import { useAuthStore } from './stores/auth' // Import the store

const authStore = useAuthStore() // Initialize store
const isLoading = ref(!authStore.isInitialized) 
const router = useRouter()

/* Watch for initialization */
watch(() => authStore.isInitialized, (newVal) => {
    if (newVal) isLoading.value = false
}, { immediate: true })

// onMounted removed - Auth init is handled by Router Guard

// --- Event Handlers ---
// Most logic is now in the store or components, but we keep high-level routing here if needed
const onLoginSuccess = (data: any) => {
  // Store handles user setting, we just redirect
  if (data.csrf_token) authStore.saveToken(data.csrf_token);
  authStore.setUser(data.user)
  router.push('/') 
}

const handleLogout = async () => {
    await authStore.logout() // Use store action
    window.location.href = '/login'; 
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
    <!-- Removed currentUser props -->
    <RouterView 
      @login-success="onLoginSuccess"
      @login-click="goToLogin"
      @logout-click="handleLogout"
      @update-user="(u: any) => authStore.setUser(u)"
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