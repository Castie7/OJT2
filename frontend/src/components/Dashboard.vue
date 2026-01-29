<script setup>
import { ref } from 'vue'
import HomeView from '../components/HomeView.vue'
import ResearchLibrary from '../components/ResearchLibrary.vue'
import MyWorkspace from '../components/MyWorkspace.vue'
import Approval from '../components/Approval.vue'

// Expecting the full user object { id, name, role }
const props = defineProps(['currentUser'])
const emit = defineEmits(['login-click', 'logout-click'])

const currentTab = ref('home')

const stats = ref([
  { title: 'Total Researches', value: '0', color: 'text-green-600' },
  { title: 'Root Crop Varieties', value: '8', color: 'text-yellow-600' },
  { title: 'Pending Reviews', value: '3', color: 'text-red-600' }
])

const updateStats = (count) => { stats.value[0].value = count }
</script>

<template>
  <div class="min-h-screen bg-gray-50 font-sans text-gray-800 relative">
    
    <nav class="bg-green-800 text-white shadow-lg sticky top-0 z-40">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
          <div class="flex items-center gap-3">
            <img src="/logo.png" alt="BSU Logo" class="h-15 w-auto object-contain hover:scale-105 transition-transform duration-300" />
            <span class="font-bold text-xl tracking-wide">BSU RootCrops</span>
          </div>
          <div class="hidden md:flex ml-10 space-x-4">
            
            <button @click="currentTab = 'home'" :class="`px-3 py-2 rounded-md text-sm font-medium transition ${currentTab === 'home' ? 'bg-green-900' : 'hover:bg-green-700'}`">Home</button>
            <button @click="currentTab = 'research'" :class="`px-3 py-2 rounded-md text-sm font-medium transition ${currentTab === 'research' ? 'bg-green-900' : 'hover:bg-green-700'}`">Research Library</button>
            
            <template v-if="currentUser">
              <button @click="currentTab = 'workspace'" :class="`px-3 py-2 rounded-md text-sm font-medium transition ${currentTab === 'workspace' ? 'bg-green-900' : 'hover:bg-green-700'}`">
                My Workspace
              </button>
              
              <button 
                v-if="currentUser.role === 'admin'" 
                @click="currentTab = 'approval'" 
                :class="`px-3 py-2 rounded-md text-sm font-medium transition ${currentTab === 'approval' ? 'bg-green-900' : 'hover:bg-green-700'}`"
              >
                Approvals
              </button>
            </template>
          </div>

          <div>
            <div v-if="currentUser" class="flex items-center gap-4">
              <span class="text-sm font-light">Welcome, {{ currentUser.name }}</span>
              <button @click="$emit('logout-click')" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded text-sm font-bold transition flex items-center gap-2">
                Logout
              </button>
            </div>
            <button v-else @click="$emit('login-click')" class="bg-yellow-500 hover:bg-yellow-600 text-green-900 px-4 py-2 rounded text-sm font-bold transition flex items-center gap-2">
               Login
            </button>
          </div>
        </div>
      </div>
    </nav>

    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
      
      <HomeView 
        v-if="currentTab === 'home'" 
        :currentUser="currentUser" 
        :stats="stats" 
        @browse-click="currentTab = 'research'" 
      />

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

    </main>
  </div>
</template>