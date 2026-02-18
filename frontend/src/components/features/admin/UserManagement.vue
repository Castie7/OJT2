<script setup lang="ts">
import { useUserManagement } from '../../../composables/useUserManagement'
import BaseButton from '../../ui/BaseButton.vue'
import BaseCard from '../../ui/BaseCard.vue'
import BaseInput from '../../ui/BaseInput.vue'
import BaseSelect from '../../ui/BaseSelect.vue'

// Initialize Logic
const { 
  users, isLoading, isSubmitting, showAddForm, form, 
  addUser, resetPassword 
} = useUserManagement()

const roleOptions = [
  { value: 'admin', label: 'Admin' },
  { value: 'user', label: 'Researcher' }
]
</script>

<template>
  <div class="p-6 space-y-6">
    
    <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
      <h2 class="text-2xl font-bold text-gray-800">User Management</h2>
      <BaseButton 
        @click="showAddForm = !showAddForm" 
        variant="primary"
      >
        {{ showAddForm ? '- Close Form' : '+ Add New User' }}
      </BaseButton>
    </div>

    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-2"
    >
      <BaseCard v-if="showAddForm" class="border-t-4 border-t-emerald-500">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
          <BaseInput
            v-model="form.name"
            label="Full Name"
            placeholder="e.g. Juan Dela Cruz"
          />
          
          <BaseInput
            v-model="form.email"
            label="Email Address"
            type="email"
            placeholder="user@school.edu.ph"
          />

          <BaseInput
            v-model="form.password"
            label="Initial Password"
            type="password"
            placeholder="••••••••"
          />

          <BaseSelect
            v-model="form.role"
            label="Role"
            :options="roleOptions"
          />
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
          <BaseButton @click="showAddForm = false" variant="ghost">Cancel</BaseButton>
          <BaseButton 
            @click="addUser" 
            :disabled="isSubmitting" 
            variant="primary"
          >
            {{ isSubmitting ? 'Saving...' : 'Create Account' }}
          </BaseButton>
        </div>
      </BaseCard>
    </transition>

    <BaseCard no-padding class="overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead class="bg-gray-50 text-gray-600 uppercase text-xs font-semibold tracking-wider border-b border-gray-200">
            <tr>
              <th class="px-6 py-4">ID</th>
              <th class="px-6 py-4">Name</th>
              <th class="px-6 py-4">Email</th>
              <th class="px-6 py-4">Role</th>
              <th class="px-6 py-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="isLoading">
              <td colspan="5" class="px-6 py-8 text-center">
                <div class="flex flex-col items-center gap-2">
                  <div class="w-6 h-6 border-2 border-emerald-500 border-t-transparent rounded-full animate-spin"></div>
                  <span class="text-sm text-gray-500 animate-pulse">Loading directory...</span>
                </div>
              </td>
            </tr>

            <tr v-else-if="users.length === 0">
              <td colspan="5" class="px-6 py-8 text-center text-gray-500">No users found.</td>
            </tr>

            <tr v-else v-for="user in users" :key="user.id" class="hover:bg-gray-50 transition-colors">
              <td class="px-6 py-4 text-sm text-gray-500">#{{ user.id }}</td>
              <td class="px-6 py-4 text-sm font-semibold text-gray-900">{{ user.name }}</td>
              <td class="px-6 py-4 text-sm text-gray-600">{{ user.email }}</td>
              <td class="px-6 py-4">
                <span 
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                  :class="user.role === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'"
                >
                  {{ user.role === 'user' ? 'Researcher' : 'Admin' }}
                </span>
              </td>
              <td class="px-6 py-4 text-right">
                <BaseButton 
                  @click="resetPassword(user.id, user.name)" 
                  variant="outline" 
                  size="sm"
                >
                  Reset Password
                </BaseButton>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseCard>

  </div>
</template>