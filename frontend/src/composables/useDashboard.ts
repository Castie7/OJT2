import { ref } from 'vue'

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
}

export function useDashboard() {
  
  // --- STATE ---
  const currentTab = ref('home')
  
  const stats = ref<Stat[]>([
    { title: 'Total Researches', value: '0', color: 'text-green-600' },
    { title: 'Root Crop Varieties', value: '8', color: 'text-yellow-600' },
    { title: 'Pending Reviews', value: '3', color: 'text-red-600' }
  ])

  // --- ACTIONS ---
  const updateStats = (count: number) => { 
    if(stats.value.length > 0) {
        stats.value[0].value = count 
    }
  }

  const setTab = (tab: string) => {
    currentTab.value = tab
  }

  return {
    currentTab,
    stats,
    updateStats,
    setTab
  }
}