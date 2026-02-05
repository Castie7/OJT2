import { ref, watch, computed, onMounted, onUnmounted, nextTick, type Ref } from 'vue'
import { API_BASE_URL } from '../apiConfig'

export interface User {
  id: number
  name: string
  role: string
  email: string
}

export interface Stat {
  id?: string
  title: string
  value: string | number
  color: string
  action?: string 
}

export function useDashboard(currentUserRef: Ref<User | null>) { 
  
  // --- 1. CORE STATE ---
  const currentTab = ref('home')
  
  // Child Component Refs (To access methods inside children)
  const workspaceRef = ref<any>(null)
  const approvalRef = ref<any>(null)

  // Default Loading State
  const stats = ref<Stat[]>([
      { id: 'stat-1', title: 'Loading...', value: '...', color: 'text-gray-400' },
      { id: 'stat-2', title: 'Root Crop Varieties', value: '8', color: 'text-yellow-600', action: 'home' },
      { id: 'stat-3', title: 'Loading...', value: '...', color: 'text-gray-400' }
  ])

  // --- 2. ADMIN MENU LOGIC ---
  const showAdminMenu = ref(false)
  const closeAdminMenu = () => { setTimeout(() => showAdminMenu.value = false, 200) }

  // --- 3. NOTIFICATION STATE ---
  const showNotifications = ref(false)
  const notifications = ref<any[]>([])
  const pollingInterval = ref<any>(null)
  const prevUnread = ref<number>(0)
  const initialized = ref(false)

  // --- 4. CORE ACTIONS ---
  const setTab = (tab: string) => {
    currentTab.value = tab
  }

  const fetchDashboardStats = async () => {
    const user = currentUserRef.value
    if (!user) return

    try {
        if (user.role === 'admin') {
            const response = await fetch(`${API_BASE_URL}/research/stats`)
            if (!response.ok) throw new Error("API Error")
            const data = await response.json()
            
            stats.value = [
                { id: 'stat-1', title: 'Total Researches', value: data.total, color: 'text-green-600', action: 'research' },
                { id: 'stat-2', title: 'Root Crop Varieties', value: '8', color: 'text-yellow-600', action: 'home' }, 
                { id: 'stat-3', title: 'Pending Reviews', value: data.pending, color: 'text-red-600', action: 'approval' }
            ]
        } else {
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

  const updateStats = (count: number) => { 
    const firstStat = stats.value[0]
    if (firstStat && firstStat.title === 'Total Researches') {
       firstStat.value = count 
    }
  }

  // --- 5. NOTIFICATION LOGIC ---

  // Audio Context (Sound)
  let audioCtx: AudioContext | null = null
  const ensureAudioContext = () => {
    if (!audioCtx) {
        try { audioCtx = new (window.AudioContext || (window as any).webkitAudioContext)() } catch (e) { audioCtx = null }
    }
    return audioCtx
  }

  const playNotificationSound = (count = 1) => {
    const ctx = ensureAudioContext()
    if (!ctx) return
    if (ctx.state === 'suspended') void ctx.resume().catch(() => {})

    const now = ctx.currentTime
    const spacing = 0.18 

    for (let i = 0; i < count; i++) {
        const o = ctx.createOscillator()
        const g = ctx.createGain()
        o.type = 'sine'
        o.frequency.value = 1000
        o.connect(g)
        g.connect(ctx.destination)

        const start = now + i * spacing
        g.gain.setValueAtTime(0, start)
        g.gain.linearRampToValueAtTime(0.28, start + 0.004)
        g.gain.exponentialRampToValueAtTime(0.001, start + 0.14)

        o.start(start)
        o.stop(start + 0.16)
    }
  }

  const unreadCount = computed(() => notifications.value.filter(n => n.is_read == 0).length)

  const fetchNotifications = async () => {
    const user = currentUserRef.value
    if (!user) return
    try {
        const response = await fetch(`${API_BASE_URL}/api/notifications?user_id=${user.id}`)
        if (response.ok) {
            notifications.value = await response.json()
        }
    } catch (error) {
        console.error("Failed to fetch notifications", error)
    }
  }

  const toggleNotifications = async () => {
    showNotifications.value = !showNotifications.value
    const user = currentUserRef.value
    
    if (showNotifications.value && unreadCount.value > 0 && user) {
        try {
            // Optimistic update
            notifications.value.forEach(n => n.is_read = 1)
            
            await fetch(`${API_BASE_URL}/api/notifications/read`, { 
                method: 'POST', 
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: user.id })
            })
        } catch (e) { console.error(e) }
    }
  }

  const handleNotificationClick = async (notif: any) => {
    if (!notif.research_id) return
    showNotifications.value = false // Close dropdown
    const user = currentUserRef.value

    // 1. If User is Admin -> Go to Approval Tab
    if (user?.role === 'admin') {
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

  // --- 6. WATCHERS & LIFECYCLE ---

  // Watch for User Changes to fetch stats
  watch(currentUserRef, (newUser) => {
     if (newUser) {
        fetchDashboardStats()
        fetchNotifications().then(() => {
            prevUnread.value = unreadCount.value
            initialized.value = true
        })
     }
  }, { immediate: true })

  // Watch for Notification Count (Sound Effect)
  watch(unreadCount, (newVal, oldVal) => {
    if (!initialized.value) return
    const prev = (typeof oldVal === 'number') ? oldVal : prevUnread.value || 0
    const diff = newVal - prev
    if (diff > 0) {
        playNotificationSound(diff)
    }
    prevUnread.value = newVal
  })

  onMounted(() => {
    // Poll for notifications every 10 seconds
    pollingInterval.value = setInterval(fetchNotifications, 10000)
  })

  onUnmounted(() => {
    if (pollingInterval.value) clearInterval(pollingInterval.value)
  })

  // --- RETURN EVERYTHING ---
  return { 
    // State
    currentTab, 
    stats, 
    workspaceRef, 
    approvalRef,
    showAdminMenu,
    showNotifications,
    notifications,
    unreadCount,

    // Actions
    setTab, 
    updateStats, 
    fetchDashboardStats,
    closeAdminMenu,
    toggleNotifications,
    handleNotificationClick,
    formatTimeAgo
  }
}