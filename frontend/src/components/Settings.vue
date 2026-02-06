<script setup lang="ts">
import { toRef } from 'vue'
import { useSettings, type User } from '../composables/useSettings'

const props = defineProps<{ currentUser: User | null }>()

// 1. Define Emits
const emit = defineEmits<{ 
  (e: 'update-user', user: User): void
  (e: 'trigger-logout'): void 
}>()

// Create a reactive reference to the prop so the composable can watch it
const currentUserRef = toRef(props, 'currentUser') 

// 2. Use Composable
// We pass callbacks so the composable can trigger parent events
const { 
  profileForm, passForm, 
  isProfileLoading, isPasswordLoading,
  saveProfile, changePassword,
  showCurrentPass, showNewPass
} = useSettings(
    currentUserRef, 
    (u) => emit('update-user', u), // Success Callback: Update Parent State
    () => emit('trigger-logout')   // Success Callback: Logout User
)
</script>

<template>
  <div class="p-6 max-w-5xl mx-auto space-y-8">
    <div class="flex items-center gap-4 mb-6">
      <h2 class="text-3xl font-bold text-gray-800">⚙️ Settings</h2>
    </div>

    <div class="settings-card">
      <div class="settings-header flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
           <h3 class="text-xl font-bold text-gray-800">👤 Account Overview</h3>
           <p class="text-sm text-gray-500">View and update your personal details.</p>
        </div>
        
        <div class="flex items-center gap-4 bg-green-50 px-5 py-3 rounded-lg border border-green-200 shadow-sm w-full md:w-auto">
           <div class="h-12 w-12 bg-green-200 text-green-800 rounded-full flex items-center justify-center font-bold text-xl border-2 border-white shadow-sm shrink-0">
             {{ currentUser?.name?.charAt(0).toUpperCase() || 'U' }}
           </div>
           <div class="flex flex-col">
             <span class="text-[10px] text-green-600 uppercase font-bold tracking-wider">Currently Logged In</span>
             <div class="font-bold text-gray-900 leading-tight">
                {{ currentUser?.name || 'Unknown User' }}
             </div>
             <div class="text-xs text-gray-600 font-medium">
                {{ currentUser?.email || 'No Email Found' }}
             </div>
           </div>
        </div>
      </div>
      
      <div class="settings-body">
        <form @submit.prevent="saveProfile">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="form-group">
              <label class="form-label">Display Name</label>
              <input v-model="profileForm.name" type="text" class="form-input" :placeholder="currentUser?.name"/>
            </div>
            <div class="form-group">
              <label class="form-label">Email Address</label>
              <input v-model="profileForm.email" type="email" class="form-input" :placeholder="currentUser?.email"/>
            </div>
          </div>
          <div class="flex justify-end mt-4">
            <button type="submit" class="btn-save" :disabled="isProfileLoading">
              {{ isProfileLoading ? 'Updating...' : 'Save Profile Info' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <div class="settings-card border-t-4 border-t-yellow-500">
      <div class="settings-header">
          <h3 class="text-xl font-bold text-gray-800">🔒 Security Zone</h3>
          <p class="text-sm text-gray-500">Update your password securely.</p>
      </div>
      
      <div class="settings-body">
        <form @submit.prevent="changePassword">
           <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200 mb-6 flex gap-3">
              <span class="text-2xl">🔑</span>
              <div>
                <h4 class="font-bold text-yellow-800 text-sm">Password Policy</h4>
                <p class="text-xs text-yellow-700 mt-1">To ensure security, you must enter your <b>Old Password</b> before creating a new one.</p>
              </div>
           </div>

           <div class="space-y-5 max-w-lg">
              <div>
                <label class="form-label">Old Password <span class="text-red-500">*</span></label>
                <div class="relative">
                  <input v-model="passForm.current" :type="showCurrentPass ? 'text' : 'password'" class="form-input pr-10" placeholder="Enter current password"/>
                  <button type="button" @click="showCurrentPass = !showCurrentPass" class="absolute inset-y-0 right-0 px-3 text-gray-400 hover:text-gray-600">
                    {{ showCurrentPass ? '🙈' : '👁️' }}
                  </button>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                 <div>
                    <label class="form-label">New Password</label>
                    <div class="relative">
                      <input v-model="passForm.new" :type="showNewPass ? 'text' : 'password'" class="form-input pr-10" placeholder="Min. 6 chars"/>
                      <button type="button" @click="showNewPass = !showNewPass" class="absolute inset-y-0 right-0 px-3 text-gray-400 hover:text-gray-600">
                        {{ showNewPass ? '🙈' : '👁️' }}
                      </button>
                    </div>
                 </div>
                 <div>
                    <label class="form-label">Confirm New</label>
                    <input v-model="passForm.confirm" :type="showNewPass ? 'text' : 'password'" class="form-input" placeholder="Repeat password"/>
                 </div>
              </div>
           </div>

           <div class="flex justify-end mt-6">
             <button type="submit" class="btn-save bg-yellow-600 hover:bg-yellow-700" :disabled="isPasswordLoading">
                {{ isPasswordLoading ? 'Verifying...' : 'Change Password' }}
             </button>
           </div>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped src="../assets/styles/Settings.css"></style>