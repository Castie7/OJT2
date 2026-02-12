import { ref, watch, nextTick, computed, onMounted } from 'vue'
import api from '../services/api' // ✅ Secure API Service

// --- TYPE DEFINITIONS ---
export interface Research {
    id: number
    title: string
    author: string
    crop_variation: string
    abstract: string
    status: 'pending' | 'approved' | 'rejected' | 'archived'
    file_path: string
    start_date?: string
    deadline_date?: string
    archived_at?: string
    approved_at?: string
    updated_at?: string
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
}

export function useSubmittedResearches(props: { currentUser: User | null, statusFilter: string }) {

    // --- STATE ---
    const myItems = ref<Research[]>([])
    const isLoading = ref(false)
    const searchQuery = ref('')
    const currentPage = ref(1)
    const itemsPerPage = 10

    // UI State
    const editingItem = ref<Research | null>(null)
    const editPdfFile = ref<File | null>(null)
    const isSaving = ref(false)
    const selectedResearch = ref<Research | null>(null)

    // Modal State
    const commentModal = ref({
        show: false,
        researchId: null as number | null,
        title: '',
        list: [] as Comment[],
        newComment: ''
    })
    const isSendingComment = ref(false)
    const chatContainer = ref<HTMLElement | null>(null)
    const confirmModal = ref({
        show: false,
        id: null as number | null,
        action: '',
        title: '',
        subtext: '',
        isProcessing: false
    })

    // --- HELPERS ---
    const getDeadlineStatus = (deadline?: string) => {
        if (!deadline) return null
        const today = new Date()
        const due = new Date(deadline)
        const diffTime = due.getTime() - today.getTime()
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))

        if (diffDays < 0) return { text: `Overdue by ${Math.abs(diffDays)} days`, color: 'text-red-600 bg-red-100' }
        if (diffDays === 0) return { text: 'Due Today!', color: 'text-red-600 font-bold bg-red-100' }
        if (diffDays <= 7) return { text: `${diffDays} days left`, color: 'text-yellow-700 bg-yellow-100' }

        return { text: due.toLocaleDateString(), color: 'text-gray-500' }
    }

    const formatSimpleDate = (dateStr?: any) => {
        if (!dateStr) return 'N/A'

        let dateVal = dateStr
        // Handle { date: "...", timezone: ... } object from backend
        if (typeof dateStr === 'object' && dateStr.date) {
            dateVal = dateStr.date
        }

        try {
            const d = new Date(dateVal)
            // Check if valid date
            if (isNaN(d.getTime())) return dateVal

            return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
        } catch (e) {
            return dateVal
        }
    }

    const getArchiveDaysLeft = (archivedDate?: string) => {
        if (!archivedDate) return 60
        const start = new Date(archivedDate)
        const expiration = new Date(start)
        expiration.setDate(start.getDate() + 60)
        const today = new Date()
        const diffTime = expiration.getTime() - today.getTime()
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
        return diffDays > 0 ? diffDays : 0
    }

    // --- FETCH DATA ---
    const fetchData = async () => {
        isLoading.value = true
        try {
            // ✅ Use api.get()
            // The service handles Base URL and Cookies automatically
            const endpoint = props.statusFilter === 'archived'
                ? '/research/my-archived'
                : '/research/my-submissions'

            const response = await api.get(endpoint)
            myItems.value = response.data
            currentPage.value = 1
            searchQuery.value = ''

        } catch (error) {
            console.error("Error fetching items:", error)
        } finally {
            isLoading.value = false
        }
    }

    // --- SEARCH & PAGINATION ---
    // --- SEARCH & PAGINATION ---
    const filteredItems = computed(() => {
        let items = myItems.value

        // 1. Filter by status (if not archived)
        if (props.statusFilter !== 'archived') {
            items = items.filter(item => item.status === props.statusFilter)
        }

        // 2. Filter by search query
        if (searchQuery.value) {
            const query = searchQuery.value.toLowerCase()
            items = items.filter(item =>
                item.title.toLowerCase().includes(query) ||
                item.author.toLowerCase().includes(query)
            )
        }
        return items
    })

    const paginatedItems = computed(() => {
        const start = (currentPage.value - 1) * itemsPerPage
        const end = start + itemsPerPage
        return filteredItems.value.slice(start, end)
    })

    const totalPages = computed(() => Math.ceil(filteredItems.value.length / itemsPerPage))

    // Watchers & Lifecycle
    watch(searchQuery, () => { currentPage.value = 1 })
    watch(() => props.statusFilter, (newVal, oldVal) => {
        // If we switch TO 'archived', or FROM 'archived', or have no items, we must fetch fresh data.
        // Because 'archived' uses a different API endpoint than the other statuses.
        if (newVal === 'archived' || oldVal === 'archived' || myItems.value.length === 0) {
            fetchData()
        }
    })
    onMounted(() => fetchData())

    const nextPage = () => { if (currentPage.value < totalPages.value) currentPage.value++ }
    const prevPage = () => { if (currentPage.value > 1) currentPage.value-- }

    // --- ACTIONS ---
    const requestArchive = (item: Research) => {
        const action = props.statusFilter === 'archived' ? 'Restore' : 'Archive'
        confirmModal.value = {
            show: true, id: item.id, action: action,
            title: action === 'Archive' ? 'Move to Trash?' : 'Restore File?',
            subtext: action === 'Archive' ? `Remove "${item.title}"?` : `Restore "${item.title}"?`,
            isProcessing: false
        }
    }

    const executeArchive = async () => {
        if (!confirmModal.value.id) return
        confirmModal.value.isProcessing = true
        try {
            // ✅ Use api.post()
            const endpoint = props.statusFilter === 'archived'
                ? `/research/restore/${confirmModal.value.id}`
                : `/research/archive/${confirmModal.value.id}`

            await api.post(endpoint)

            confirmModal.value.show = false
            fetchData()

        } catch (e) {
            alert("Action Failed.")
        } finally {
            confirmModal.value.isProcessing = false
        }
    }

    // --- COMMENTS ---
    const openComments = async (item: Research) => {
        commentModal.value = { show: true, researchId: item.id, title: item.title, list: [], newComment: '' }
        try {
            // ✅ Use api.get()
            const res = await api.get(`/research/comments/${item.id}`)
            commentModal.value.list = res.data
            nextTick(() => { if (chatContainer.value) chatContainer.value.scrollTop = chatContainer.value.scrollHeight })
        } catch (e) { }
    }

    const postComment = async () => {
        if (isSendingComment.value || !commentModal.value.newComment.trim() || !props.currentUser) return
        isSendingComment.value = true
        try {
            // ✅ Use api.post()
            // CSRF token is attached automatically by interceptor
            await api.post('/api/comments', {
                research_id: commentModal.value.researchId,
                user_id: props.currentUser.id,
                user_name: props.currentUser.name,
                role: props.currentUser.role,
                comment: commentModal.value.newComment
            })

            // Refresh comments
            const refreshRes = await api.get(`/research/comments/${commentModal.value.researchId}`)
            commentModal.value.list = refreshRes.data
            commentModal.value.newComment = ''
            nextTick(() => { if (chatContainer.value) chatContainer.value.scrollTop = chatContainer.value.scrollHeight })
        } catch (e: any) {
            alert("Failed: " + (e.response?.data?.message || e.message))
        } finally {
            isSendingComment.value = false
        }
    }

    // --- EDIT LOGIC ---
    const openEdit = (item: Research) => { editingItem.value = { ...item }; editPdfFile.value = null }

    const handleEditFile = (e: Event) => {
        const target = e.target as HTMLInputElement
        const file = target.files?.[0]
        if (!file) { editPdfFile.value = null; return }

        const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png']
        if (!allowedExtensions.includes(file.name.split('.').pop()?.toLowerCase() || '')) {
            alert("❌ Invalid File!")
            target.value = ''
            editPdfFile.value = null
            return
        }
        editPdfFile.value = file
    }

    const saveEdit = async () => {
        if (isSaving.value || !editingItem.value) return
        const item = editingItem.value

        if (!item.title.trim() || !item.author.trim() || !item.deadline_date) {
            alert("⚠️ Missing Fields")
            return
        }

        isSaving.value = true
        const formData = new FormData()
        formData.append('title', item.title)
        formData.append('author', item.author)
        formData.append('abstract', item.abstract || '')
        formData.append('start_date', item.start_date || '')
        formData.append('deadline_date', item.deadline_date)
        if (editPdfFile.value) formData.append('pdf_file', editPdfFile.value)

        try {
            // ✅ Use api.post() for FormData
            // Axios handles multipart/form-data automatically when passed FormData
            await api.post(`/research/update/${item.id}`, formData)

            alert("✅ Updated!")
            editingItem.value = null
            fetchData()

        } catch (e) {
            alert("Server Error")
        } finally {
            isSaving.value = false
        }
    }

    return {
        myItems, isLoading, searchQuery, currentPage, itemsPerPage,
        editingItem, isSaving, selectedResearch, commentModal, isSendingComment,
        chatContainer, confirmModal,
        fetchData, filteredItems, paginatedItems, totalPages, nextPage, prevPage,
        requestArchive, executeArchive, openComments, postComment,
        openEdit, handleEditFile, saveEdit,
        getDeadlineStatus, formatSimpleDate, getArchiveDaysLeft
    }
}