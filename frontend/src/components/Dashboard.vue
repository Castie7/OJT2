<script setup lang="ts">
import { toRef, ref, onMounted, onUnmounted, computed, nextTick } from 'vue' 
import { useDashboard, type User } from '../composables/useDashboard'
import { API_BASE_URL } from '../apiConfig' // ✅ Imported Central Config

// Import Sub-Components
import HomeView from '../components/Homeview.vue'
import ResearchLibrary from '../components/ResearchLibrary.vue'
import MyWorkspace from '../components/MyWorkspace.vue'
import Approval from '../components/Approval.vue'
import Settings from '../components/Settings.vue' 
import ImportCsv from '../components/ImportCsv.vue' 

const props = defineProps<{
  currentUser: User | null
}>()

const emit = defineEmits<{
  (e: 'login-click'): void
  (e: 'logout-click'): void
  (e: 'update-user', user: User): void 
}>()

// --- Core Dashboard Logic ---
const currentUserRef = toRef(props, 'currentUser')
const { currentTab, stats, updateStats, setTab } = useDashboard(currentUserRef)

// --- Component Refs (To access methods inside children) ---
const workspaceRef = ref<any>(null)
const approvalRef = ref<any>(null)

// --- NEW: Admin Menu Logic ---
const showAdminMenu = ref(false)
// Close menu with a small delay so clicks register before blur
const closeAdminMenu = () => { setTimeout(() => showAdminMenu.value = false, 200) }

const handleUserUpdate = (updatedUser: User) => {
  emit('update-user', updatedUser)
}

// --- Notification Logic ---
const showNotifications = ref(false)
const notifications = ref<any[]>([])
const pollingInterval = ref<any>(null)

// Computed count
const unreadCount = computed(() => {
  return notifications.value.filter(n => n.is_read == 0).length
})

const fetchNotifications = async () => {
  if (!props.currentUser) return
  try {
    // ✅ Uses centralized API_BASE_URL
    const response = await fetch(`${API_BASE_URL}/api/notifications?user_id=${props.currentUser.id}`)
    if (response.ok) {
      notifications.value = await response.json()
    }
  } catch (error) {
    console.error("Failed to fetch notifications", error)
  }
}

const toggleNotifications = async () => {
  showNotifications.value = !showNotifications.value
  
  if (showNotifications.value && unreadCount.value > 0) {
    try {
        // Optimistic update
        notifications.value.forEach(n => n.is_read = 1)
        
        // ✅ Uses centralized API_BASE_URL
        await fetch(`${API_BASE_URL}/api/notifications/read`, { 
            method: 'POST', 
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: props.currentUser?.id })
        })
    } catch (e) { console.error(e) }
  }
}

// --- CLICK HANDLER ---
const handleNotificationClick = async (notif: any) => {
    if (!notif.research_id) return
    
    showNotifications.value = false // Close dropdown

    // 1. If User is Admin -> Go to Approval Tab
    if (props.currentUser?.role === 'admin') {
        setTab('approval')
        await nextTick() 
        if (approvalRef.value) {
            approvalRef.value.openNotification(notif.research_id)
        }
    } 
    // 2. If User is Student -> Go to Workspace Tab
    else {
        setTab('workspace')
        await nextTick() 
        if (workspaceRef.value) {
            workspaceRef.value.openNotification(notif.research_id)
        }
    }
}

const formatTimeAgo = (dateString: string) => {
  const date = new Date(dateString)
  const now = new Date()
  const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000)
  
  if (diffInSeconds < 60) return 'Just now'
  if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`
  if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`
  return date.toLocaleDateString()
}

onMounted(() => {
  fetchNotifications()
  pollingInterval.value = setInterval(fetchNotifications, 10000)
})

onUnmounted(() => {
  if (pollingInterval.value) clearInterval(pollingInterval.value)
})
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
              
              <template v-if="currentUser.role === 'admin'">
                <button 
                  @click="setTab('approval')" 
                  :class="['nav-btn', currentTab === 'approval' ? 'nav-btn-active' : 'nav-btn-inactive']"
                >
                  Approvals
                </button>

                <div class="relative group">
                    <button 
                        @click="showAdminMenu = !showAdminMenu" 
                        @blur="closeAdminMenu"
                        :class="['nav-btn flex items-center gap-1', (currentTab === 'import' || showAdminMenu) ? 'nav-btn-active' : 'nav-btn-inactive']"
                    >
                        Admin Tools ▾
                    </button>

                    <div v-if="showAdminMenu" class="absolute top-full left-0 mt-1 w-64 bg-white rounded-lg shadow-xl border border-gray-100 overflow-hidden text-sm z-50 animate-fade-in">
                        <div class="py-1">
                            
                            <button 
                                @click="setTab('import'); showAdminMenu = false"
                                class="w-full text-left px-4 py-3 text-gray-700 hover:bg-green-50 hover:text-green-700 font-bold border-l-4 border-transparent hover:border-green-600 transition flex items-center gap-2"
                            >
                                📂 Upload Data Researches
                            </button>
                            
                            <div class="border-t border-gray-100 my-1"></div>

                            <button disabled class="w-full text-left px-4 py-3 text-gray-400 cursor-not-allowed flex justify-between items-center hover:bg-gray-50">
                                <div class="flex items-center gap-2">
                                    <span>👥 Add/Reset Accounts</span>
                                </div>
                                <span class="text-[9px] uppercase font-bold bg-gray-200 text-gray-500 px-1.5 py-0.5 rounded">Soon</span>
                            </button>

                            <button disabled class="w-full text-left px-4 py-3 text-gray-400 cursor-not-allowed flex justify-between items-center hover:bg-gray-50">
                                <div class="flex items-center gap-2">
                                    <span>✏️ Edit Master List</span>
                                </div>
                                <span class="text-[9px] uppercase font-bold bg-gray-200 text-gray-500 px-1.5 py-0.5 rounded">Soon</span>
                            </button>

                            <button disabled class="w-full text-left px-4 py-3 text-gray-400 cursor-not-allowed flex justify-between items-center hover:bg-gray-50">
                                <div class="flex items-center gap-2">
                                    <span>📜 Activity Logs</span>
                                </div>
                                <span class="text-[9px] uppercase font-bold bg-gray-200 text-gray-500 px-1.5 py-0.5 rounded">Soon</span>
                            </button>

                        </div>
                    </div>
                </div>
              </template>
            </template>
          </div>

          <div>
            <div v-if="currentUser" class="flex items-center gap-4">
              <span class="text-sm font-light hidden sm:block">Welcome, {{ currentUser.name }}</span>
              
              <div class="relative">
                <button 
                  @click="toggleNotifications" 
                  class="p-2 rounded-full hover:bg-green-700 transition relative focus:outline-none"
                  title="Notifications"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                  </svg>
                  
                  <span 
                    v-if="unreadCount > 0" 
                    class="absolute top-1 right-1 h-4 w-4 bg-red-500 rounded-full text-[10px] font-bold flex items-center justify-center border border-green-800"
                  >
                    {{ unreadCount }}
                  </span>
                </button>

                <div v-if="showNotifications" class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-xl overflow-hidden z-50 border border-gray-100 animate-fade-in">
                  <div class="bg-gray-50 px-4 py-2 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="text-sm font-bold text-gray-700">Notifications</h3>
                    <span class="text-xs text-gray-500 cursor-pointer hover:text-green-600">Mark all read</span>
                  </div>
                  
                  <div class="max-h-64 overflow-y-auto custom-scrollbar">
                    <div v-if="notifications.length === 0" class="p-4 text-center text-gray-400 text-sm">
                      No new notifications.
                    </div>
                    
                    <div 
                      v-for="notif in notifications" 
                      :key="notif.id" 
                      @click="handleNotificationClick(notif)"
                      class="px-4 py-3 border-b border-gray-50 hover:bg-green-50 transition cursor-pointer flex gap-3 items-start"
                      :class="{'bg-blue-50/30': notif.is_read == 0}"
                    >
                      <div class="mt-1 text-xl">💬</div>
                      <div>
                        <p class="text-sm text-gray-800 leading-tight">{{ notif.message }}</p>
                        <span class="text-[10px] text-gray-400 font-medium">{{ formatTimeAgo(notif.created_at) }}</span>
                      </div>
                    </div>

                  </div>
                </div>
              </div>

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
        @stat-click="setTab"
      />

      <ResearchLibrary 
        v-if="currentTab === 'research'" 
        :currentUser="currentUser" 
        @update-stats="updateStats" 
      />

      <MyWorkspace 
        v-if="currentTab === 'workspace'" 
        ref="workspaceRef"
        :currentUser="currentUser" 
      />

      <Approval 
        v-if="currentTab === 'approval' && currentUser && currentUser.role === 'admin'" 
        ref="approvalRef"
        :currentUser="currentUser" 
      />

      <ImportCsv 
        v-if="currentTab === 'import' && currentUser && currentUser.role === 'admin'"
        @upload-success="setTab('research')" 
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

<style scoped>
.animate-fade-in {
  animation: fadeIn 0.2s ease-out;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-5px); }
  to { opacity: 1; transform: translateY(0); }
}

.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #cbd5e1; 
  border-radius: 4px;
}
</style>