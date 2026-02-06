import { ref, computed, watch, onMounted } from 'vue'
import api from '../services/api' // ✅ Uses Secure API Service

// --- 1. SHARED INTERFACES ---
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
  role: string
  email: string
}

export function useResearchLibrary(currentUser: User | null, emit: (event: 'update-stats', count: number) => void) {
  
  // --- STATE ---
  const researches = ref<Research[]>([])
  
  const searchQuery = ref('')
  const selectedType = ref('') // Dropdown Filter
  
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

  const formatSimpleDate = (dateStr?: string) => {
    if (!dateStr) return 'N/A'
    return new Date(dateStr).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
  }

  // --- API FETCH ---
  const fetchResearches = async () => {
    isLoading.value = true
    try {
      // ✅ Use api.get()
      // The secure service handles Base URL and Cookies automatically.
      const endpoint = showArchived.value 
        ? '/research/archived' 
        : '/research'

      const response = await api.get(endpoint)
      
      researches.value = response.data
      
      if (!showArchived.value) {
        emit('update-stats', researches.value.length)
      }

    } catch (error: any) {
      console.error(error)
      // Check for specific error codes if needed
      if (showArchived.value && error.response?.status === 403) {
          showToast("Access Denied to Archives", "error")
      } else {
          showToast("Failed to load data.", "error")
      }
    } finally {
      isLoading.value = false
    }
  }

  // --- FILTERING LOGIC ---
  const filteredResearches = computed(() => {
    return researches.value.filter(item => {
      // A. Search Query (Title, Author, ISBN, Subjects)
      const q = searchQuery.value.toLowerCase()
      const matchesSearch = 
        item.title.toLowerCase().includes(q) || 
        item.author.toLowerCase().includes(q) ||
        (item.isbn_issn && item.isbn_issn.toLowerCase().includes(q)) ||
        (item.subjects && item.subjects.toLowerCase().includes(q))

      // B. Knowledge Type Filter
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
    
    try {
      // ✅ Use api.post()
      // CSRF headers are automatically handled by the api.ts interceptor
      const endpoint = confirmModal.value.action === 'Restore'
        ? `/research/restore/${confirmModal.value.id}`
        : `/research/archive/${confirmModal.value.id}`

      await api.post(endpoint)
      
      fetchResearches() 
      showToast(`Item ${confirmModal.value.action}d successfully!`, "success")
      confirmModal.value.show = false

    } catch (error: any) {
       console.error(error)
       const msg = error.response?.data?.message || "Error updating status"
       showToast("Failed: " + msg, "error")
    }
  }

  // --- WATCHERS ---
  
  // Reload when switching between Active/Archived
  watch(showArchived, () => {
    currentPage.value = 1
    fetchResearches()
  })

  // Reset pagination on filter change
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
    selectedType, 
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