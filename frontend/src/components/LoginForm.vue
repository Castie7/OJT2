<script setup>
import { ref } from 'vue'

// 1. Add 'back' to the allowed signals
const emit = defineEmits(['login-success', 'back'])

const email = ref('')
const password = ref('')
const message = ref('')
const isSuccess = ref(false)
const isLoading = ref(false) // Added loading state for button animation

const handleLogin = async () => {
  isLoading.value = true
  message.value = ""
  
  try {
    const response = await fetch('http://localhost:8080/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ 
        email: email.value, 
        password: password.value 
      })
    })

    const data = await response.json()
    
    if (data.status === 'success') {
      isSuccess.value = true
      message.value = "Login Successful!"
      
      setTimeout(() => {
        // Send the WHOLE data object (contains user AND token)
        emit('login-success', data)
      }, 1000)
    } else {
      isSuccess.value = false
      message.value = data.message || "Invalid credentials"
      isLoading.value = false
    }

  } catch (error) {
    message.value = "Server connection failed."
    isLoading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-green-900 via-green-800 to-yellow-900 relative overflow-hidden font-sans">
    
    <div class="absolute top-[-10%] left-[-10%] w-96 h-96 bg-green-500 rounded-full mix-blend-overlay filter blur-3xl opacity-20 animate-blob"></div>
    <div class="absolute bottom-[-10%] right-[-10%] w-96 h-96 bg-yellow-500 rounded-full mix-blend-overlay filter blur-3xl opacity-20 animate-blob animation-delay-2000"></div>

    <div class="relative w-full max-w-md bg-white/95 backdrop-blur-sm rounded-2xl shadow-2xl overflow-hidden transform transition-all hover:scale-[1.01] duration-300">
      
      <div class="h-2 w-full bg-yellow-400"></div>

      <div class="p-8">
        
        <div class="text-center mb-8">
          <div class="h-20 w-20 bg-green-100 rounded-full mx-auto flex items-center justify-center shadow-inner mb-4 border-2 border-yellow-400">
            <span class="text-4xl">🌿</span>
          </div>
          <h2 class="text-3xl font-bold text-green-900 tracking-tight">Research Portal</h2>
          <p class="text-sm text-green-600 font-medium uppercase tracking-wide mt-1">BSU RootCrops Research</p>
        </div>
        
        <form @submit.prevent="handleLogin" class="space-y-6">
          
          <div class="group">
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1 ml-1 group-focus-within:text-green-600 transition-colors">Email Address</label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">✉️</span>
              <input 
                v-model="email" 
                type="email" 
                class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all outline-none text-gray-800 placeholder-gray-400" 
                placeholder="admin@bsu.edu.ph" 
                required 
              />
            </div>
          </div>

          <div class="group">
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1 ml-1 group-focus-within:text-green-600 transition-colors">Password</label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">🔒</span>
              <input 
                v-model="password" 
                type="password" 
                class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all outline-none text-gray-800 placeholder-gray-400" 
                placeholder="••••••••" 
                required 
              />
            </div>
          </div>
          
          <button 
            type="submit" 
            :disabled="isLoading"
            :class="`w-full font-bold py-3 px-4 rounded-lg shadow-lg transform transition-all active:scale-95 flex justify-center items-center gap-2 ${isLoading || isSuccess ? 'bg-green-700 cursor-not-allowed' : 'bg-green-800 hover:bg-green-700 hover:shadow-green-900/30 text-white'}`"
          >
            <span v-if="isLoading && !isSuccess" class="animate-spin h-5 w-5 border-2 border-white border-t-transparent rounded-full"></span>
            <span v-if="!isLoading">Sign In</span>
            <span v-if="isLoading && isSuccess">Redirecting...</span>
          </button>

          <div class="relative flex py-2 items-center">
            <div class="flex-grow border-t border-gray-200"></div>
            <span class="flex-shrink-0 mx-4 text-gray-400 text-xs">OR</span>
            <div class="flex-grow border-t border-gray-200"></div>
          </div>

          <button 
            type="button" 
            @click="$emit('back')" 
            class="w-full bg-white hover:bg-gray-50 text-gray-600 font-semibold py-2 px-4 rounded-lg border border-gray-200 transition-colors text-sm hover:text-green-700"
          >
            ← Return to Website
          </button>

        </form>

        <Transition name="fade">
          <div v-if="message" :class="`mt-6 p-3 rounded-lg text-sm font-medium text-center border flex items-center justify-center gap-2 ${isSuccess ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-600 border-red-200'}`">
            <span>{{ isSuccess ? '✅' : '⚠️' }}</span>
            {{ message }}
          </div>
        </Transition>

      </div>
      
      <div class="h-1 w-full bg-gradient-to-r from-green-800 via-yellow-400 to-green-800"></div>
    </div>

    <div class="absolute bottom-4 text-green-200/60 text-xs text-center w-full">
      &copy; {{ new Date().getFullYear() }} Benguet State University. All Rights Reserved.
    </div>

  </div>
</template>

<style scoped>
/* Custom Animations for the Background Blobs */
@keyframes blob {
  0% { transform: translate(0px, 0px) scale(1); }
  33% { transform: translate(30px, -50px) scale(1.1); }
  66% { transform: translate(-20px, 20px) scale(0.9); }
  100% { transform: translate(0px, 0px) scale(1); }
}
.animate-blob {
  animation: blob 7s infinite;
}
.animation-delay-2000 {
  animation-delay: 2s;
}

/* Fade Transition for Messages */
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>