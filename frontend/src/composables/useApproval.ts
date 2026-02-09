import { ref, computed, nextTick, onMounted } from 'vue'
import api from '../services/api' // ✅ Switch to Secure API Service

// --- TYPES ---
export interface Research {
  id: number
  title: string
  author: string
  deadline_date: string
  rejected_at?: string
  file_path: string
  created_at: string
}

export interface User {
  id: number
  name: string
  role: string
}

interface Comment {
  id: number
  user_name: string
  role: string
  comment: string
  created_at: string
}

export function useApproval(currentUser: User | null) {

  // --- STATE ---
  const activeTab = ref<'pending' | 'rejected'>('pending')
  const items = ref<Research[]>([])
  const isLoading = ref(false)
  const selectedResearch = ref<Research | null>(null)

  const currentPage = ref(1)
  const itemsPerPage = 10

  // Modals
  const deadlineModal = ref({ show: false, id: null as number | null, title: '', currentDate: '', newDate: '' })
  const commentModal = ref({ show: false, researchId: null as number | null, title: '', list: [] as Comment[], newComment: '' })
  const isSendingComment = ref(false)
  const chatContainer = ref<HTMLElement | null>(null)

  // --- HELPERS ---

  // (Removed getHeaders() because api.ts handles it automatically)

  const formatDate = (dateString?: any) => {
    if (!dateString) return 'No Date'

    let dateVal = dateString
    // Handle { date: "...", timezone: ... } object from backend
    if (typeof dateString === 'object' && dateString.date) {
      dateVal = dateString.date
    }

    try {
      const d = new Date(dateVal)
      if (isNaN(d.getTime())) return dateVal // Fallback to raw string if invalid

      return d.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      })
    } catch {
      return dateVal
    }
  }

  const formatSimpleDate = formatDate

  const getDaysLeft = (rejectedDate?: string) => {
    if (!rejectedDate) return 30
    const rejected = new Date(rejectedDate)
    const expiration = new Date(rejected)
    expiration.setDate(rejected.getDate() + 30)
    const today = new Date()
    const diffTime = expiration.getTime() - today.getTime()
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
    return diffDays > 0 ? diffDays : 0
  }

  // --- API ACTIONS ---

  const fetchData = async () => {
    isLoading.value = true
    items.value = []
    try {
      // ✅ Use api.get()
      // The service handles the Base URL automatically
      const endpoint = activeTab.value === 'pending'
        ? '/research/pending'
        : '/research/rejected'

      const response = await api.get(endpoint)
      items.value = response.data
      currentPage.value = 1

    } catch (error) {
      console.error("Error fetching data:", error)
    } finally {
      isLoading.value = false
    }
  }

  const handleAction = async (id: number, action: 'approve' | 'reject' | 'restore') => {
    const msg = action === 'restore'
      ? 'Are you sure you want to RESTORE this item to Pending?'
      : `Are you sure you want to ${action} this research?`

    if (!confirm(msg)) return

    try {
      let endpoint = ''
      if (action === 'approve') endpoint = 'approve'
      else if (action === 'reject') endpoint = 'reject'
      else if (action === 'restore') endpoint = 'restore'

      // ✅ Use api.post()
      await api.post(`/research/${endpoint}/${id}`)

      alert(`Action ${action} successful!`)
      fetchData()
    } catch (error) {
      alert("Action failed")
    }
  }

  const paginatedItems = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage
    const end = start + itemsPerPage
    return items.value.slice(start, end)
  })

  const totalPages = computed(() => Math.ceil(items.value.length / itemsPerPage))
  const nextPage = () => { if (currentPage.value < totalPages.value) currentPage.value++ }
  const prevPage = () => { if (currentPage.value > 1) currentPage.value-- }

  // --- DEADLINE LOGIC ---
  const openDeadlineModal = (item: Research) => {
    deadlineModal.value = { show: true, id: item.id, title: item.title, currentDate: item.deadline_date, newDate: item.deadline_date }
  }

  const saveNewDeadline = async () => {
    if (!deadlineModal.value.newDate || !deadlineModal.value.id) return
    try {
      const formData = new FormData()
      formData.append('new_deadline', deadlineModal.value.newDate)

      // ✅ Use api.post() with FormData
      await api.post(`/research/extend-deadline/${deadlineModal.value.id}`, formData)

      alert("Deadline Updated!")
      deadlineModal.value.show = false
      fetchData()
    } catch (e) {
      alert("Server Error: Failed to update deadline.")
    }
  }

  // --- COMMENTS LOGIC ---
  const scrollToBottom = () => { nextTick(() => { if (chatContainer.value) chatContainer.value.scrollTop = chatContainer.value.scrollHeight }) }

  const openComments = async (item: Research) => {
    commentModal.value = { show: true, researchId: item.id, title: item.title, list: [], newComment: '' }
    try {
      // ✅ Use api.get()
      const res = await api.get(`/research/comments/${item.id}`)
      commentModal.value.list = res.data
      scrollToBottom()
    } catch (e) {
      console.error("Error loading comments")
    }
  }

  const postComment = async () => {
    if (isSendingComment.value || !commentModal.value.newComment.trim() || !currentUser) return
    isSendingComment.value = true
    try {
      // ✅ Use api.post()
      // Automatically adds JSON headers and CSRF token
      await api.post('/api/comments', {
        research_id: commentModal.value.researchId,
        user_id: currentUser.id,
        user_name: currentUser.name,
        role: 'admin',
        comment: commentModal.value.newComment
      })

      // Refresh comments
      const refreshRes = await api.get(`/research/comments/${commentModal.value.researchId}`)
      commentModal.value.list = refreshRes.data
      commentModal.value.newComment = ''
      scrollToBottom()

    } catch (e: any) {
      alert("Failed: " + (e.response?.data?.message || e.message))
    } finally {
      isSendingComment.value = false
    }
  }

  onMounted(() => fetchData())

  return {
    activeTab, items, isLoading, selectedResearch,
    currentPage, itemsPerPage, paginatedItems, totalPages, nextPage, prevPage,
    deadlineModal, commentModal, isSendingComment, chatContainer,
    fetchData, handleAction, formatDate, getDaysLeft,
    openDeadlineModal, saveNewDeadline, openComments, postComment,
    formatSimpleDate
  }
}