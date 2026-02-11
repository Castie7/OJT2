import { ref, computed, watch, onMounted } from 'vue'
import api from '../services/api'

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
    // Details
    knowledge_type?: string
    publication_date?: string
    edition?: string
    publisher?: string
    physical_description?: string
    isbn_issn?: string
    subjects?: string
    shelf_location?: string
    item_condition?: string
    link?: string
}

export interface User {
    id: number
    name: string
    role: string
    email?: string
}

export function useMasterlist() {

    // --- STATE ---
    const allItems = ref<Research[]>([])
    const isLoading = ref(false)
    const searchQuery = ref('')
    const statusFilter = ref('ALL')
    const currentPage = ref(1)
    const itemsPerPage = 10

    // Edit Modal State
    const isEditModalOpen = ref(false)
    const isSaving = ref(false)
    const editForm = ref<any>({
        id: null,
        title: '',
        author: '',
        crop_variation: '',
        start_date: '',
        deadline_date: '',
        knowledge_type: [] as string[],
        publication_date: '',
        edition: '',
        publisher: '',
        physical_description: '',
        isbn_issn: '',
        subjects: '',
        shelf_location: '',
        item_condition: 'Good',
        link: '',
        pdf_file: null as File | null
    })

    // --- FETCH ---
    const fetchData = async () => {
        isLoading.value = true
        try {
            const response = await api.get('/research/masterlist')
            allItems.value = response.data
            currentPage.value = 1
        } catch (error) {
            console.error('Failed to fetch masterlist:', error)
        } finally {
            isLoading.value = false
        }
    }

    // --- SEARCH & FILTER ---
    const filteredItems = computed(() => {
        let items = allItems.value

        // Status filter
        if (statusFilter.value !== 'ALL') {
            items = items.filter(i => i.status === statusFilter.value.toLowerCase())
        }

        // Search filter
        if (searchQuery.value) {
            const query = searchQuery.value.toLowerCase()
            items = items.filter(i =>
                i.title.toLowerCase().includes(query) ||
                i.author.toLowerCase().includes(query) ||
                (i.crop_variation && i.crop_variation.toLowerCase().includes(query))
            )
        }

        return items
    })

    // --- PAGINATION ---
    const paginatedItems = computed(() => {
        const start = (currentPage.value - 1) * itemsPerPage
        return filteredItems.value.slice(start, start + itemsPerPage)
    })

    const totalPages = computed(() => Math.ceil(filteredItems.value.length / itemsPerPage))
    const nextPage = () => { if (currentPage.value < totalPages.value) currentPage.value++ }
    const prevPage = () => { if (currentPage.value > 1) currentPage.value-- }

    // Reset page when filters change
    watch([searchQuery, statusFilter], () => { currentPage.value = 1 })

    // --- EDIT ---
    const openEdit = (item: Research) => {
        editForm.value = {
            id: item.id,
            title: item.title,
            author: item.author,
            crop_variation: item.crop_variation || '',
            start_date: item.start_date || '',
            deadline_date: item.deadline_date || '',
            knowledge_type: item.knowledge_type ? item.knowledge_type.split(',').map(s => s.trim()) : [],
            publication_date: item.publication_date || '',
            edition: item.edition || '',
            publisher: item.publisher || '',
            physical_description: item.physical_description || '',
            isbn_issn: item.isbn_issn || '',
            subjects: item.subjects || '',
            shelf_location: item.shelf_location || '',
            item_condition: item.item_condition || 'Good',
            link: item.link || '',
            pdf_file: null
        }
        isEditModalOpen.value = true
    }

    const handleFileChange = (e: Event) => {
        const target = e.target as HTMLInputElement
        const file = target.files?.[0]
        if (!file) { editForm.value.pdf_file = null; return }

        const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png']
        const fileExtension = file.name.split('.').pop()?.toLowerCase() || ''

        if (!allowedExtensions.includes(fileExtension)) {
            alert('❌ Invalid File!\nPlease upload a PDF or an Image.')
            target.value = ''
            editForm.value.pdf_file = null
            return
        }
        editForm.value.pdf_file = file
    }

    const saveEdit = async (): Promise<boolean> => {
        if (isSaving.value || !editForm.value.id) return false
        const form = editForm.value

        if (!form.title.trim()) { alert('⚠️ Title is required.'); return false }
        if (!form.author.trim()) { alert('⚠️ Author is required.'); return false }

        isSaving.value = true
        const formData = new FormData()

        formData.append('title', form.title)
        formData.append('author', form.author)
        formData.append('crop_variation', form.crop_variation)
        formData.append('start_date', form.start_date)
        formData.append('deadline_date', form.deadline_date)

        const kType = Array.isArray(form.knowledge_type) ? form.knowledge_type.join(', ') : form.knowledge_type
        formData.append('knowledge_type', kType)
        formData.append('publication_date', form.publication_date)
        formData.append('edition', form.edition)
        formData.append('publisher', form.publisher)
        formData.append('physical_description', form.physical_description)
        formData.append('isbn_issn', form.isbn_issn)
        formData.append('subjects', form.subjects)
        formData.append('shelf_location', form.shelf_location)
        formData.append('item_condition', form.item_condition)
        formData.append('link', form.link)

        if (form.pdf_file) formData.append('pdf_file', form.pdf_file)

        try {
            await api.post(`/research/update/${form.id}`, formData)
            alert('✅ Research updated successfully!')
            isEditModalOpen.value = false
            fetchData()
            return true
        } catch (error: any) {
            let msg = 'Update Failed'
            if (error.response?.data?.messages) {
                msg = Object.values(error.response.data.messages).join('\n')
            } else if (error.response?.data?.message) {
                msg = error.response.data.message
            }
            alert('❌ Error:\n' + msg)
            return false
        } finally {
            isSaving.value = false
        }
    }

    // --- HELPERS ---
    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'approved': return { label: '✅ Published', classes: 'bg-green-100 text-green-700 border-green-200' }
            case 'pending': return { label: '⏳ Pending', classes: 'bg-yellow-100 text-yellow-800 border-yellow-200' }
            case 'rejected': return { label: '❌ Rejected', classes: 'bg-red-100 text-red-700 border-red-200' }
            default: return { label: status, classes: 'bg-gray-100 text-gray-700 border-gray-200' }
        }
    }

    const formatDate = (dateStr?: any) => {
        if (!dateStr) return 'N/A'
        let dateVal = dateStr
        if (typeof dateStr === 'object' && dateStr.date) dateVal = dateStr.date
        try {
            const d = new Date(dateVal)
            if (isNaN(d.getTime())) return dateVal
            return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
        } catch { return dateVal }
    }

    const isRefreshing = ref(false)

    const resetFilters = async () => {
        searchQuery.value = ''
        statusFilter.value = 'ALL'
        currentPage.value = 1
        isRefreshing.value = true
        try {
            await fetchData()
        } finally {
            setTimeout(() => { isRefreshing.value = false }, 500)
        }
    }

    // --- LIFECYCLE ---
    onMounted(() => fetchData())

    return {
        allItems, isLoading, isRefreshing, searchQuery, statusFilter,
        currentPage, itemsPerPage, filteredItems, paginatedItems, totalPages,
        nextPage, prevPage,
        isEditModalOpen, isSaving, editForm,
        fetchData, openEdit, handleFileChange, saveEdit,
        getStatusBadge, formatDate, resetFilters
    }
}
