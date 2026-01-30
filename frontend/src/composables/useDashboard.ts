import { ref, onMounted } from 'vue'

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
  action?: string // <--- This allows the Dashboard to know where to navigate
}

export function useDashboard() {
  
  // Default tab
  const currentTab = ref('home')
  
  // Stats Array with Actions
  const stats = ref<Stat[]>([
    { 
      title: 'Total Researches', 
      value: '...', 
      color: 'text-green-600', 
      action: 'research' // Clicking this switches currentTab to 'research'
    },
    { 
      title: 'Root Crop Varieties', 
      value: '8', 
      color: 'text-yellow-600', 
      action: 'home'     // Clicking this stays on home
    }, 
    { 
      title: 'Pending Reviews', 
      value: '...', 
      color: 'text-red-600', 
      action: 'approval' // Clicking this switches currentTab to 'approval'
    }
  ])

  // Navigation Helper
  const setTab = (tab: string) => {
    currentTab.value = tab
  }

  // Fetch Logic
  const fetchDashboardStats = async () => {
    try {
        const response = await fetch('http://localhost:8080/research/stats')
        if (response.ok) {
            const data = await response.json()
            
            // Update "Total Researches" (Index 0)
            stats.value[0].value = data.total
            
            // Update "Pending Reviews" (Index 2)
            stats.value[2].value = data.pending
        }
    } catch (e) {
        console.error("Failed to load stats")
    }
  }

  // Fetch immediately on load
  onMounted(() => {
    fetchDashboardStats()
  })

  // Helper for manual updates (e.g. if the library list changes)
  const updateStats = (count: number) => { 
    stats.value[0].value = count 
  }

  return {
    currentTab,
    stats,
    updateStats,
    setTab,
    fetchDashboardStats
  }
}