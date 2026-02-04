import { ref, watch, type Ref } from 'vue'
import { API_BASE_URL } from '../apiConfig' // ✅ Imported Central Configuration

export interface User {
  id: number
  name: string
  role: string
  email: string
}

export interface Stat {
  id?: string // Add unique identifier to prevent key conflicts
  title: string
  value: string | number
  color: string
  action?: string 
}

export function useDashboard(currentUserRef: Ref<User | null>) { 
  
  const currentTab = ref('home')
  
  // Default Loading State
  const stats = ref<Stat[]>([
      { id: 'stat-1', title: 'Loading...', value: '...', color: 'text-gray-400' },
      { id: 'stat-2', title: 'Root Crop Varieties', value: '8', color: 'text-yellow-600', action: 'home' },
      { id: 'stat-3', title: 'Loading...', value: '...', color: 'text-gray-400' }
  ])

  const setTab = (tab: string) => {
    currentTab.value = tab
  }

  const fetchDashboardStats = async () => {
    const user = currentUserRef.value
    if (!user) return

    try {
        // === ADMIN LOGIC ===
        if (user.role === 'admin') {
            // ✅ Uses Centralized API_BASE_URL
            const response = await fetch(`${API_BASE_URL}/research/stats`)
            if (!response.ok) throw new Error("API Error")
            
            const data = await response.json()
            
            stats.value = [
                { id: 'stat-1', title: 'Total Researches', value: data.total, color: 'text-green-600', action: 'research' },
                { id: 'stat-2', title: 'Root Crop Varieties', value: '8', color: 'text-yellow-600', action: 'home' }, 
                { id: 'stat-3', title: 'Pending Reviews', value: data.pending, color: 'text-red-600', action: 'approval' }
            ]
        } 
        // === RESEARCHER LOGIC ===
        else {
            // ✅ Uses Centralized API_BASE_URL
            const response = await fetch(`${API_BASE_URL}/research/user-stats/${user.id}`)
            if (!response.ok) throw new Error("API Error")
            
            const data = await response.json()

            stats.value = [
                { id: 'stat-1', title: 'My Published Works', value: data.published, color: 'text-green-600', action: 'workspace' },
                { id: 'stat-2', title: 'Root Crop Varieties', value: '8', color: 'text-yellow-600', action: 'home' },
                { id: 'stat-3', title: 'My Pending Submissions', value: data.pending, color: 'text-orange-500', action: 'workspace' }
            ]
        }
    } catch (e) {
        console.error("Stats Fetch Failed:", e)
        
        // ✅ FIX: Check if elements exist before assigning
        if (stats.value[0]) {
            stats.value[0].title = "Connection Failed"
            stats.value[0].value = "Error"
            stats.value[0].color = "text-red-500"
        }
        
        if (stats.value[2]) {
            stats.value[2].title = "Connection Failed"
            stats.value[2].value = "Error"
            stats.value[2].color = "text-red-500"
        }
    }
  }

  watch(currentUserRef, (newUser) => {
     if (newUser) fetchDashboardStats()
  }, { immediate: true })

  const updateStats = (count: number) => { 
    // ✅ FIX: Assign to a variable first to ensure it's not undefined
    const firstStat = stats.value[0]
    
    if (firstStat && firstStat.title === 'Total Researches') {
       firstStat.value = count 
    }
  }

  return { currentTab, stats, updateStats, setTab, fetchDashboardStats }
}