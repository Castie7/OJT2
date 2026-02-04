import { ref, computed, watch, onMounted } from 'vue'
import { API_BASE_URL } from '../apiConfig' // ✅ Imported Central Configuration

// --- 1. UPDATED INTERFACE (Matches your DB) ---
export interface Research {
  id: number
  title: string
  author: string
  status: 'pending' | 'approved' | 'rejected' | 'archived'
  
  // Library Catalog Fields
  knowledge_type: string
  crop_variation: string
  publication_date: string
  edition: string
  publisher: string
  physical_description: string
  isbn_issn: string
  subjects: string
  shelf_location: string
  item_condition: string
  link: string
  file_path: string
  
  // Dates
  approved_at?: string
  archived_at?: string
  created_at: string
}

export interface User {
  id: number
  name: string
  role: 'admin' | 'user'
  email: string
}

export function useResearchLibrary(currentUser: User | null, emit: (event: 'update-stats', count: number) => void) {
  
  // --- STATE ---
  const researches = ref<Research[]>([])
  
  const searchQuery = ref('')
  const selectedType = ref('') // <--- NEW: For the Dropdown Filter (Default: All)
  
  const showArchived = ref(false)
  const viewMode = ref<'list' | 'grid'>('list')
  const selectedResearch = ref<Research | null>(null)
  
  // UI State
  const isLoading = ref(false)
  const toast = ref({ show: false, message: '', type: 'success' as 'success' | 'error' })
  const confirmModal = ref({ 
    show: false, 
    id: null as number | null, 
    action: '', 
    title: '', 
    subtext: '' 
  })

  // Pagination State
  const currentPage = ref(1)
  const itemsPerPage = 10

  // --- HELPERS ---
  const showToast = (message: string, type: 'success' | 'error' = 'success') => {
    toast.value = { show: true, message, type }
    setTimeout(() => { toast.value.show = false }, 3000)
  }

  const getCookie = (name: string): string | null => {
    const value = `; ${document.cookie}`
    const parts = value.split(`; ${name}=`)
    if (parts.length === 2) return parts.pop()?.split(';').shift() || null
    return null
  }

  const formatSimpleDate = (dateStr?: string) => {
    if (!dateStr) return 'N/A'
    return new Date(dateStr).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
  }

  // --- API FETCH ---
  const fetchResearches = async () => {
    isLoading.value = true
    try {
      // ✅ Uses Centralized API_BASE_URL
      const endpoint = showArchived.value 
        ? `${API_BASE_URL}/research/archived` 
        : `${API_BASE_URL}/research`

      const token = getCookie('auth_token')
      const headers: HeadersInit = token ? { 'Authorization': token } : {}

      const response = await fetch(endpoint, { headers })
      
      if (response.ok) {
        const data: Research[] = await response.json()
        researches.value = data
        
        if (!showArchived.value) {
          emit('update-stats', data.length)
        }
      } else {
         if(showArchived.value) showToast("Access Denied to Archives", "error")
      }
    } catch (error) {
      console.error(error)
      showToast("Failed to load data.", "error")
    } finally {
      isLoading.value = false
    }
  }

  // --- 2. UPDATED FILTERING LOGIC ---
  const filteredResearches = computed(() => {
    return researches.value.filter(item => {
      // A. Search Query (Expanded to include ISBN and Subjects)
      const q = searchQuery.value.toLowerCase()
      const matchesSearch = 
        item.title.toLowerCase().includes(q) || 
        item.author.toLowerCase().includes(q) ||
        (item.isbn_issn && item.isbn_issn.toLowerCase().includes(q)) ||
        (item.subjects && item.subjects.toLowerCase().includes(q))

      // B. Knowledge Type Filter
      // If selectedType is empty string, we return true (match all)
      const matchesType = selectedType.value === '' || item.knowledge_type === selectedType.value

      return matchesSearch && matchesType
    })
  })

  const paginatedResearches = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage
    const end = start + itemsPerPage
    return filteredResearches.value.slice(start, end)
  })

  const totalPages = computed(() => Math.ceil(filteredResearches.value.length / itemsPerPage))

  // --- ACTIONS ---
  const nextPage = () => { if (currentPage.value < totalPages.value) currentPage.value++ }
  const prevPage = () => { if (currentPage.value > 1) currentPage.value-- }

  const requestArchiveToggle = (item: Research) => {
    const action = showArchived.value ? 'Restore' : 'Archive'
    confirmModal.value = {
      show: true,
      id: item.id,
      action: action,
      title: action === 'Archive' ? 'Move to Trash?' : 'Restore Research?',
      subtext: action === 'Archive' ? `Remove "${item.title}"?` : `Restore "${item.title}"?`
    }
  }

  const executeArchiveToggle = async () => {
    if (!confirmModal.value.id) return
    
    const token = getCookie('auth_token')
    if (!token) { showToast("Authentication Error", "error"); return }

    try {
      // ✅ Uses Centralized API_BASE_URL
      const endpoint = confirmModal.value.action === 'Restore'
        ? `${API_BASE_URL}/research/restore/${confirmModal.value.id}`
        : `${API_BASE_URL}/research/archive/${confirmModal.value.id}`

      const response = await fetch(endpoint, { 
        method: 'POST',
        headers: { 'Authorization': token } 
      })
      
      if (response.ok) {
          fetchResearches() 
          showToast(`Item ${confirmModal.value.action}d successfully!`, "success")
          confirmModal.value.show = false
      } else {
          const err = await response.json()
          showToast("Failed: " + (err.message || "Access Denied"), "error")
      }
    } catch (error) { showToast("Error updating status", "error") }
  }

  // --- WATCHERS ---
  
  // Watch for endpoint changes (Active vs Archived)
  watch(showArchived, () => {
    currentPage.value = 1
    fetchResearches()
  })

  // Watch for local filter changes to reset pagination
  watch([searchQuery, selectedType], () => {
    currentPage.value = 1
  })

  onMounted(() => {
    fetchResearches()
  })

  return {
    // State
    researches, 
    searchQuery, 
    selectedType, // <--- EXPORTED THIS
    showArchived, 
    viewMode, 
    selectedResearch,
    isLoading, 
    toast, 
    confirmModal, 
    currentPage, 
    itemsPerPage,
    
    // Computed
    filteredResearches, 
    paginatedResearches, 
    totalPages,
    
    // Methods
    fetchResearches, 
    nextPage, 
    prevPage, 
    requestArchiveToggle, 
    executeArchiveToggle,
    formatSimpleDate, 
    showToast
  }
}