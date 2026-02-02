import { ref, computed, nextTick, onMounted } from 'vue'

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
  
  const activeTab = ref<'pending' | 'rejected'>('pending')
  const items = ref<Research[]>([])
  const isLoading = ref(false)
  const selectedResearch = ref<Research | null>(null)
  
  const currentPage = ref(1)
  const itemsPerPage = 10

  const deadlineModal = ref({ show: false, id: null as number | null, title: '', currentDate: '', newDate: '' })
  const commentModal = ref({ show: false, researchId: null as number | null, title: '', list: [] as Comment[], newComment: '' })
  const isSendingComment = ref(false)
  const chatContainer = ref<HTMLElement | null>(null)

  const getHeaders = () => {
    const token = document.cookie.split('; ').find(row => row.startsWith('auth_token='))?.split('=')[1]
    return { 'Authorization': token || '' }
  }

  // --- 1. UPDATED DATE FORMATTER (Standardized) ---
  const formatDate = (dateString?: string) => {
    if (!dateString) return 'No Date'
    // Converts "2026-02-07" -> "Feb 7, 2026"
    return new Date(dateString).toLocaleDateString('en-US', { 
      year: 'numeric', 
      month: 'short', 
      day: 'numeric' 
    })
  }

  // Use the same logic for created_at (Aliases to the function above)
  const formatSimpleDate = formatDate 

  const getDaysLeft = (rejectedDate?: string) => {
    if(!rejectedDate) return 30
    const rejected = new Date(rejectedDate)
    const expiration = new Date(rejected)
    expiration.setDate(rejected.getDate() + 30) 
    const today = new Date()
    const diffTime = expiration.getTime() - today.getTime()
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
    return diffDays > 0 ? diffDays : 0
  }

  const fetchData = async () => {
    isLoading.value = true
    items.value = [] 
    try {
      const endpoint = activeTab.value === 'pending' 
        ? 'http://localhost:8080/research/pending' 
        : 'http://localhost:8080/research/rejected'

      const response = await fetch(endpoint, { headers: getHeaders() })
      if (response.ok) {
        items.value = await response.json()
        currentPage.value = 1 
      }
    } catch (error) { console.error("Error fetching data:", error) } 
    finally { isLoading.value = false }
  }

  const handleAction = async (id: number, action: 'approve' | 'reject' | 'restore') => {
    const msg = action === 'restore' 
      ? 'Are you sure you want to RESTORE this item to Pending?' 
      : `Are you sure you want to ${action} this research?`

    if (!confirm(msg)) return

    try {
      let endpoint = ''
      if(action === 'approve') endpoint = 'approve'
      else if(action === 'reject') endpoint = 'reject'
      else if(action === 'restore') endpoint = 'restore'

      await fetch(`http://localhost:8080/research/${endpoint}/${id}`, { 
        method: 'POST', headers: getHeaders()
      })
      alert(`Action ${action} successful!`)
      fetchData() 
    } catch (error) { alert("Action failed") }
  }

  const paginatedItems = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage
    const end = start + itemsPerPage
    return items.value.slice(start, end)
  })

  const totalPages = computed(() => Math.ceil(items.value.length / itemsPerPage))
  const nextPage = () => { if (currentPage.value < totalPages.value) currentPage.value++ }
  const prevPage = () => { if (currentPage.value > 1) currentPage.value-- }

  const openDeadlineModal = (item: Research) => {
    deadlineModal.value = { show: true, id: item.id, title: item.title, currentDate: item.deadline_date, newDate: item.deadline_date }
  }

  const saveNewDeadline = async () => {
    if (!deadlineModal.value.newDate) return
    try {
      const formData = new FormData() 
      formData.append('new_deadline', deadlineModal.value.newDate)
      
      const res = await fetch(`http://localhost:8080/research/extend-deadline/${deadlineModal.value.id}`, {
        method: 'POST', headers: getHeaders(), body: formData
      })
      if (res.ok) { alert("Deadline Updated!"); deadlineModal.value.show = false; fetchData(); } 
      else { alert("Failed to update.") }
    } catch (e) { alert("Server Error") }
  }

  const scrollToBottom = () => { nextTick(() => { if (chatContainer.value) chatContainer.value.scrollTop = chatContainer.value.scrollHeight }) }

  const openComments = async (item: Research) => {
    commentModal.value = { show: true, researchId: item.id, title: item.title, list: [], newComment: '' }
    try {
      const res = await fetch(`http://localhost:8080/research/comments/${item.id}`, { headers: getHeaders() })
      if(res.ok) { commentModal.value.list = await res.json(); scrollToBottom() }
    } catch (e) { console.error("Error loading comments") }
  }

  const postComment = async () => {
    if (isSendingComment.value || !commentModal.value.newComment.trim() || !currentUser) return
    isSendingComment.value = true 
    try {
      // ✅ FIXED: Pointing to the correct API endpoint
      await fetch('http://localhost:8080/api/comments', {
        method: 'POST', headers: { 'Content-Type': 'application/json', ...getHeaders() },
        body: JSON.stringify({ 
            research_id: commentModal.value.researchId, 
            user_id: currentUser.id, 
            user_name: currentUser.name, 
            role: 'admin', 
            comment: commentModal.value.newComment 
        })
      })
      const refreshRes = await fetch(`http://localhost:8080/research/comments/${commentModal.value.researchId}`, { headers: getHeaders() })
      commentModal.value.list = await refreshRes.json() 
      commentModal.value.newComment = ''
      scrollToBottom()
    } catch (e: any) { alert("Failed: " + e.message) } 
    finally { isSendingComment.value = false }
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