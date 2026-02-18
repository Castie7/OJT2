import { ref, computed, watch, onMounted } from 'vue'
import { researchService } from '../services'
import { useToast } from './useToast'
import { useErrorHandler } from './useErrorHandler'
import { debounce } from '../utils/debounce'
import type { User, Research } from '../types'


export function useResearchLibrary(_currentUser: User | null, emit: (event: 'update-stats', count: number) => void) {

  // --- STATE ---
  const researches = ref<Research[]>([])

  const searchQuery = ref('')
  const selectedType = ref('') // Dropdown Filter
  const startDate = ref('') // Date Filter
  const endDate = ref('') // Date Filter


  const showArchived = ref(false)
  const viewMode = ref<'list' | 'grid'>('list')
  const selectedResearch = ref<Research | null>(null)

  // UI State
  const isLoading = ref(false)
  const { showToast } = useToast()
  const { handleError } = useErrorHandler()
  const confirmModal = ref({
    show: false,
    id: null as number | null,
    action: '',
    title: '',
    subtext: '',
    isProcessing: false
  })

  // Pagination State
  const currentPage = ref(1)
  const itemsPerPage = 10

  // --- HELPERS ---


  const formatSimpleDate = (dateStr?: string) => {
    if (!dateStr) return 'N/A'
    return new Date(dateStr).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
  }

  // --- API FETCH ---
  const fetchResearches = async () => {
    isLoading.value = true
    try {
      const filters = {
        start_date: startDate.value,
        end_date: endDate.value
      }

      researches.value = showArchived.value
        ? await researchService.getArchived(filters)
        : await researchService.getAll(filters)

      if (!showArchived.value) {
        emit('update-stats', researches.value.length)
      }

    } catch (error: any) {
      if (showArchived.value && error.response?.status === 403) {
        showToast('Access Denied to Archives', 'error')
      } else {
        handleError(error, 'Failed to load data')
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
      // Handle comma-separated values (e.g. "Journal, Book")
      const matchesType = selectedType.value === '' ||
        (item.knowledge_type && item.knowledge_type.includes(selectedType.value))

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
      subtext: action === 'Archive' ? `Remove "${item.title}"?` : `Restore "${item.title}"?`,
      isProcessing: false
    }
  }

  const executeArchiveToggle = async () => {
    if (!confirmModal.value.id || confirmModal.value.isProcessing) return

    confirmModal.value.isProcessing = true
    try {
      if (confirmModal.value.action === 'Restore') {
        await researchService.restore(confirmModal.value.id)
      } else {
        await researchService.archive(confirmModal.value.id)
      }

      fetchResearches()
      showToast(`Item ${confirmModal.value.action}d successfully!`, "success")
      confirmModal.value.show = false

    } catch (error: any) {
      handleError(error, 'Failed to update status')
    } finally {
      confirmModal.value.isProcessing = false
    }
  }

  // --- WATCHERS ---

  // Reload when switching between Active/Archived
  watch(showArchived, () => {
    currentPage.value = 1
    fetchResearches()
  })

  // Reset pagination on client-side filter change (no API call needed)
  watch([searchQuery, selectedType], () => {
    currentPage.value = 1
  })

  // Debounce date-filter changes that trigger API calls
  const debouncedFetch = debounce(() => {
    currentPage.value = 1
    fetchResearches()
  }, 400)
  watch([startDate, endDate], () => debouncedFetch())

  // Clear all filters
  const clearFilters = () => {
    searchQuery.value = ''
    selectedType.value = ''
    startDate.value = ''
    endDate.value = ''
    currentPage.value = 1
  }

  onMounted(() => {
    fetchResearches()
  })

  return {
    // State
    researches,
    searchQuery,
    selectedType,
    startDate,
    endDate,
    showArchived,
    viewMode,
    selectedResearch,
    isLoading,
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
    showToast,
    clearFilters
  }
}