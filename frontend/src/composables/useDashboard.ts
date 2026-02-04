import { ref, watch, type Ref } from 'vue'
import { API_BASE_URL } from '../apiConfig' // ✅ Imported Central Configuration

export interface User {
  id: number
  name: string
  role: string
  email: string
}

export interface Stat {
  title: string
  value: string | number
  color: string
  action?: string 
}

export function useDashboard(currentUserRef: Ref<User | null>) { 
  
  const currentTab = ref('home')
  
  // Default Loading State
  const stats = ref<Stat[]>([
      { title: 'Loading...', value: '...', color: 'text-gray-400' },
      { title: 'Root Crop Varieties', value: '8', color: 'text-yellow-600', action: 'home' },
      { title: 'Loading...', value: '...', color: 'text-gray-400' }
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
                { title: 'Total Researches', value: data.total, color: 'text-green-600', action: 'research' },
                { title: 'Root Crop Varieties', value: '8', color: 'text-yellow-600', action: 'home' }, 
                { title: 'Pending Reviews', value: data.pending, color: 'text-red-600', action: 'approval' }
            ]
        } 
        // === RESEARCHER LOGIC ===
        else {
            // ✅ Uses Centralized API_BASE_URL
            const response = await fetch(`${API_BASE_URL}/research/user-stats/${user.id}`)
            if (!response.ok) throw new Error("API Error")
            
            const data = await response.json()

            stats.value = [
                { title: 'My Published Works', value: data.published, color: 'text-green-600', action: 'workspace' },
                { title: 'Root Crop Varieties', value: '8', color: 'text-yellow-600', action: 'home' },
                { title: 'My Pending Submissions', value: data.pending, color: 'text-orange-500', action: 'workspace' }
            ]
        }
    } catch (e) {
        console.error("Stats Fetch Failed:", e)
        stats.value[0].title = "Connection Failed"
        stats.value[2].title = "Connection Failed"
    }
  }

  watch(currentUserRef, (newUser) => {
     if (newUser) fetchDashboardStats()
  }, { immediate: true })

  const updateStats = (count: number) => { 
    if (stats.value.length > 0 && stats.value[0].title === 'Total Researches') {
       stats.value[0].value = count 
    }
  }

  return { currentTab, stats, updateStats, setTab, fetchDashboardStats }
}