<script setup lang="ts">
// ✅ Logic is now moved to Composable
import { useUserManagement } from '../../../composables/useUserManagement'

// Initialize Logic
const { 
  users, isLoading, isSubmitting, showAddForm, form, 
  addUser, resetPassword 
} = useUserManagement()
</script>

<template>
  <div class="um-container">
    
    <div class="um-header">
      <h2 class="um-title">User Management</h2>
      <button @click="showAddForm = !showAddForm" class="um-btn-add">
        {{ showAddForm ? '- Close Form' : '+ Add New User' }}
      </button>
    </div>

    <div v-if="showAddForm" class="um-form-card">
      <div class="um-form-grid">
        <div class="um-input-group">
          <label class="um-label">Full Name</label>
          <input v-model="form.name" type="text" class="um-input" placeholder="e.g. Juan Dela Cruz" />
        </div>
        
        <div class="um-input-group">
          <label class="um-label">Email Address</label>
          <input v-model="form.email" type="email" class="um-input" placeholder="user@school.edu.ph" />
        </div>

        <div class="um-input-group">
          <label class="um-label">Initial Password</label>
          <input v-model="form.password" type="password" class="um-input" placeholder="••••••••" />
        </div>

        <div class="um-input-group">
          <label class="um-label">Role</label>
          <select v-model="form.role" class="um-select">
            <option value="admin">Admin</option>
            <option value="user">Researcher</option>
          </select>
        </div>
      </div>

      <div class="um-form-actions">
        <button @click="showAddForm = false" class="um-btn-cancel">Cancel</button>
        <button @click="addUser" :disabled="isSubmitting" class="um-btn-save">
          {{ isSubmitting ? 'Saving...' : 'Create Account' }}
        </button>
      </div>
    </div>

    <div class="um-table-wrapper">
      <table class="um-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="isLoading">
            <td colspan="5" style="text-align: center; padding: 20px;">
              <span class="animate-pulse">Loading user directory...</span>
            </td>
          </tr>

          <tr v-else-if="users.length === 0">
            <td colspan="5" style="text-align: center; padding: 20px;">No users found.</td>
          </tr>

          <tr v-else v-for="user in users" :key="user.id">
            <td>#{{ user.id }}</td>
            <td style="font-weight: 600; color: #1a202c;">{{ user.name }}</td>
            <td>{{ user.email }}</td>
            <td>
              <span :class="['um-badge', `role-${user.role}`]">
                {{ user.role === 'user' ? 'Researcher' : 'Admin' }}
              </span>
            </td>
            <td>
              <button @click="resetPassword(user.id, user.name)" class="um-btn-reset">
                Reset Password
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

  </div>
</template>

<style scoped src="../../../assets/styles/UserManagement.css"></style>