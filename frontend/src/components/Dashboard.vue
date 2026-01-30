<script setup lang="ts">
import { useDashboard, type User } from '../composables/useDashboard'

// Import Sub-Components
import HomeView from '../components/Homeview.vue'
import ResearchLibrary from '../components/ResearchLibrary.vue'
import MyWorkspace from '../components/MyWorkspace.vue'
import Approval from '../components/Approval.vue'
import Settings from '../components/Settings.vue' // <--- IMPORT SETTINGS

const props = defineProps<{
  currentUser: User | null
}>()

const emit = defineEmits<{
  (e: 'login-click'): void
  (e: 'logout-click'): void
  (e: 'update-user', user: User): void // New event to pass updates up to App.vue
}>()

// Use Composable
const { currentTab, stats, updateStats, setTab } = useDashboard()

// Handle Profile Updates locally + emit up
const handleUserUpdate = (updatedUser: User) => {
  emit('update-user', updatedUser)
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 font-sans text-gray-800 relative">
    
    <nav class="bg-green-800 text-white shadow-lg sticky top-0 z-40">
      <div class="nav-container">
        <div class="flex items-center justify-between h-16">
          
          <div class="flex items-center gap-3">
            <img src="/logo.png" alt="BSU Logo" class="nav-logo-img" />
            <span class="font-bold text-xl tracking-wide hidden sm:block">BSU RootCrops</span>
          </div>

          <div class="hidden md:flex ml-10 space-x-4">
            <button @click="setTab('home')" :class="['nav-btn', currentTab === 'home' ? 'nav-btn-active' : 'nav-btn-inactive']">
              Home
            </button>
            <button @click="setTab('research')" :class="['nav-btn', currentTab === 'research' ? 'nav-btn-active' : 'nav-btn-inactive']">
              Research Library
            </button>
            
            <template v-if="currentUser">
              <button @click="setTab('workspace')" :class="['nav-btn', currentTab === 'workspace' ? 'nav-btn-active' : 'nav-btn-inactive']">
                My Workspace
              </button>
              
              <button 
                v-if="currentUser.role === 'admin'" 
                @click="setTab('approval')" 
                :class="['nav-btn', currentTab === 'approval' ? 'nav-btn-active' : 'nav-btn-inactive']"
              >
                Approvals
              </button>
            </template>
          </div>

          <div>
            <div v-if="currentUser" class="flex items-center gap-4">
              <span class="text-sm font-light hidden sm:block">Welcome, {{ currentUser.name }}</span>
              
              <button @click="setTab('settings')" class="btn-settings" title="Settings">
                ⚙️
              </button>

              <button @click="$emit('logout-click')" class="btn-logout">
                Logout
              </button>
            </div>
            
            <button v-else @click="$emit('login-click')" class="btn-login">
               Login
            </button>
          </div>
        </div>
      </div>
    </nav>

    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      
  <HomeView 
    v-if="currentTab === 'home'" 
    :currentUser="currentUser" 
    :stats="stats" 
    @browse-click="setTab('research')"
    @stat-click="setTab"  />

      <ResearchLibrary 
        v-if="currentTab === 'research'" 
        :currentUser="currentUser" 
        @update-stats="updateStats" 
      />

      <MyWorkspace 
        v-if="currentTab === 'workspace'" 
        :currentUser="currentUser" 
      />

      <Approval 
        v-if="currentTab === 'approval' && currentUser && currentUser.role === 'admin'" 
        :currentUser="currentUser" 
      />

      <Settings 
        v-if="currentTab === 'settings'"
        :currentUser="currentUser"
        @update-user="handleUserUpdate"
        @trigger-logout="$emit('logout-click')" 
      />

    </main>
  </div>
</template>

<style scoped src="../assets/styles/Dashboard.css"></style>