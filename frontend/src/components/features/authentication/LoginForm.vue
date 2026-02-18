<script setup lang="ts">
import { useLoginForm } from '../../../composables/useLoginForm'
import BaseInput from '../../ui/BaseInput.vue'
import BaseButton from '../../ui/BaseButton.vue'

// 1. Define Emits
const emit = defineEmits<{
  (e: 'login-success', data: any): void
  (e: 'back'): void
}>()

// 2. Use Composable
const { 
  email, 
  password, 
  message, 
  isSuccess, 
  isLoading, 
  isLockedOut,
  handleLogin 
} = useLoginForm(emit)
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-50 relative overflow-hidden font-sans">
    
    <!-- Background Decor -->
    <div class="absolute inset-0 bg-gradient-to-br from-emerald-900 via-emerald-800 to-teal-900 opacity-100"></div>
    <div class="absolute top-[-20%] left-[-10%] w-[600px] h-[600px] bg-emerald-500 rounded-full mix-blend-overlay filter blur-[100px] opacity-20 animate-blob"></div>
    <div class="absolute bottom-[-20%] right-[-10%] w-[600px] h-[600px] bg-teal-400 rounded-full mix-blend-overlay filter blur-[100px] opacity-20 animate-blob animation-delay-2000"></div>

    <div class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden border border-white/10 backdrop-blur-xl">
      
      <!-- Header -->
      <div class="bg-emerald-900/5 p-8 text-center border-b border-gray-100">
          <div class="w-20 h-20 bg-white rounded-full mx-auto flex items-center justify-center shadow-lg mb-4 border-4 border-emerald-50 relative overflow-hidden group">
            <img 
              src="/logo.svg" 
              alt="Logo" 
              class="w-full h-full object-contain p-1 group-hover:scale-110 transition-transform duration-500" 
            />
          </div>
          <h2 class="text-2xl font-bold text-gray-900 tracking-tight">Welcome Back</h2>
          <p class="text-sm text-gray-500 mt-1">Sign in to BSU RootCrops Research Portal</p>
      </div>

      <div class="p-8 pt-6">
        <form @submit.prevent="handleLogin" class="space-y-5">
          
          <BaseInput 
            v-model="email" 
            label="Email Address" 
            placeholder="admin@bsu.edu.ph" 
            type="email"
            class="w-full"
          />

          <BaseInput 
            v-model="password" 
            label="Password" 
            placeholder="••••••••" 
            type="password"
            class="w-full"
          />
          
          <div class="pt-2">
            <BaseButton 
                type="submit" 
                :disabled="isLoading || isLockedOut"
                variant="primary"
                class="w-full justify-center py-3 text-base shadow-lg shadow-emerald-900/20"
            >
                <span v-if="isLoading && !isSuccess" class="animate-spin h-5 w-5 border-2 border-white border-t-transparent rounded-full mr-2"></span>
                {{ isLoading ? (isSuccess ? 'Redirecting...' : 'Signing In...') : 'Sign In' }}
            </BaseButton>
          </div>

          <div class="relative flex py-1 items-center">
            <div class="flex-grow border-t border-gray-100"></div>
            <span class="flex-shrink-0 mx-4 text-gray-400 text-xs font-medium uppercase tracking-widest">or</span>
            <div class="flex-grow border-t border-gray-100"></div>
          </div>

          <button 
            type="button" 
            @click="$emit('back')" 
            class="w-full text-gray-500 hover:text-emerald-700 text-sm font-medium transition-colors flex items-center justify-center gap-1 group"
          >
            <span class="group-hover:-translate-x-1 transition-transform">←</span> Return to Website
          </button>

        </form>

        <Transition name="fade">
          <div v-if="message" :class="`mt-6 p-3 rounded-lg text-sm font-medium text-center flex items-center justify-center gap-2 animate-fade-in ${isSuccess ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-red-50 text-red-600 border border-red-100'}`">
            <span>{{ isSuccess ? '✅' : '⚠️' }}</span>
            {{ message }}
          </div>
        </Transition>

      </div>
      
      <!-- Footer Decoration -->
      <div class="h-1.5 w-full bg-gradient-to-r from-emerald-600 via-teal-500 to-emerald-600"></div>
    </div>

    <div class="absolute bottom-6 text-white/40 text-[10px] text-center w-full tracking-wider">
      &copy; {{ new Date().getFullYear() }} Benguet State University. All Rights Reserved.
    </div>

  </div>
</template>

<style scoped>
.animate-blob {
  animation: blob 10s infinite;
}
.animation-delay-2000 {
  animation-delay: 2s;
}
@keyframes blob {
  0% { transform: translate(0px, 0px) scale(1); }
  33% { transform: translate(30px, -50px) scale(1.1); }
  66% { transform: translate(-20px, 20px) scale(0.9); }
  100% { transform: translate(0px, 0px) scale(1); }
}

.animate-fade-in {
    animation: fadeIn 0.3s ease-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>