import { ref, computed, watch, onMounted } from 'vue'
import { researchService } from '../services'
import { useToast } from './useToast'
import { useErrorHandler } from './useErrorHandler'
import type { Research } from '../types'

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
    const { showToast } = useToast()
    const { handleError } = useErrorHandler()
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

    // View Details Modal
    const selectedItem = ref<Research | null>(null)
    const viewDetails = (item: Research) => {
        selectedItem.value = item
    }
    const closeDetails = () => {
        selectedItem.value = null
    }

    // Confirm Modal
    const confirmModal = ref({
        show: false,
        id: null as number | null,
        action: '',
        title: '',
        subtext: '',
        isProcessing: false
    })

    // --- FETCH ---
    // --- FETCH ---
    const fetchData = async () => {
        isLoading.value = true
        try {
            // The original code fetched '/research/masterlist' which implies a specific endpoint. 
            // We now have a dedicated service method for this.
            allItems.value = await researchService.getMasterlist()
            currentPage.value = 1
        } catch (error) {
            handleError(error, 'Failed to load masterlist')
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

    // Helper function to convert date to YYYY-MM-DD format for date inputs
    const toDateInputFormat = (dateStr?: any) => {
        if (!dateStr) return ''
        let dateVal = dateStr
        // Handle DateTime objects from backend
        if (typeof dateStr === 'object' && dateStr.date) dateVal = dateStr.date
        try {
            const d = new Date(dateVal)
            if (isNaN(d.getTime())) return ''
            // Format to YYYY-MM-DD
            const year = d.getFullYear()
            const month = String(d.getMonth() + 1).padStart(2, '0')
            const day = String(d.getDate()).padStart(2, '0')
            return `${year}-${month}-${day}`
        } catch {
            return ''
        }
    }

    // --- EDIT ---
    const openEdit = (item: Research) => {
        editForm.value = {
            id: item.id,
            title: item.title,
            author: item.author,
            crop_variation: item.crop_variation || '',
            start_date: toDateInputFormat(item.start_date),
            deadline_date: toDateInputFormat(item.deadline_date),
            knowledge_type: item.knowledge_type ? item.knowledge_type.split(',').map(s => s.trim()) : [],
            publication_date: toDateInputFormat(item.publication_date),
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
            showToast('Invalid File! Please upload a PDF or an Image.', 'error')
            target.value = ''
            editForm.value.pdf_file = null
            return
        }
        editForm.value.pdf_file = file
    }

    const saveEdit = async (): Promise<boolean> => {
        if (isSaving.value || !editForm.value.id) return false
        const form = editForm.value

        if (!form.title.trim()) { showToast('Title is required.', 'warning'); return false }
        if (!form.author.trim()) { showToast('Author is required.', 'warning'); return false }

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
            await researchService.update(form.id, formData)
            showToast('Research updated successfully!', 'success')
            isEditModalOpen.value = false
            fetchData()
            return true
        } catch (error: any) {
            let msg = 'Update Failed'
            const errData = error.response?.data
            if (errData?.messages) {
                msg = Object.values(errData.messages).join('\n')
            } else if (errData?.message) {
                msg = errData.message
            }
            showToast('Error: ' + msg, 'error')
            return false
        } finally {
            isSaving.value = false
        }
    }

    // --- ARCHIVE / RESTORE ---
    const requestArchive = (item: Research) => {
        const action = item.status === 'archived' ? 'Restore' : 'Archive'
        confirmModal.value = {
            show: true,
            id: item.id,
            action: action,
            title: action === 'Archive' ? 'Move to Trash?' : 'Restore Item?',
            subtext: action === 'Archive' ? `Remove "${item.title}" from Masterlist?` : `Restore "${item.title}" to active list?`,
            isProcessing: false
        }
    }

    const executeArchive = async () => {
        if (!confirmModal.value.id || confirmModal.value.isProcessing) return

        confirmModal.value.isProcessing = true
        try {
            if (confirmModal.value.action === 'Restore') {
                await researchService.restore(confirmModal.value.id)
            } else if (confirmModal.value.action === 'Archive') {
                await researchService.archive(confirmModal.value.id)
            } else if (confirmModal.value.action === 'Approve') {
                await researchService.approve(confirmModal.value.id)
            } else if (confirmModal.value.action === 'Reject') {
                await researchService.reject(confirmModal.value.id)
            }

            showToast(`${confirmModal.value.action} successful!`, 'success')
            confirmModal.value.show = false

            // If we approved/rejected from details modal, close it
            if (['Approve', 'Reject'].includes(confirmModal.value.action) && selectedItem.value) {
                closeDetails()
            }

            fetchData()
        } catch (error: any) {
            const msg = error.response?.data?.message || 'Action failed'
            showToast(`Error: ${msg}`, 'error')
        } finally {
            confirmModal.value.isProcessing = false
        }
    }

    // --- HELPERS ---
    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'approved': return { label: '✅ Published', classes: 'bg-green-100 text-green-700 border-green-200' }
            case 'pending': return { label: '⏳ Pending', classes: 'bg-yellow-100 text-yellow-800 border-yellow-200' }
            case 'rejected': return { label: '❌ Rejected', classes: 'bg-red-100 text-red-700 border-red-200' }
            case 'archived': return { label: '🗑️ Archived', classes: 'bg-gray-200 text-gray-600 border-gray-300' }
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

    // Approve / Reject
    const approveResearch = (id: number) => {
        confirmModal.value = {
            show: true,
            id: id,
            action: 'Approve',
            title: '✅ Approve Research?',
            subtext: 'This item will be marked as Approved and visible to the public.',
            isProcessing: false
        }
    }

    const rejectResearch = (id: number) => {
        confirmModal.value = {
            show: true,
            id: id,
            action: 'Reject',
            title: '❌ Reject Research?',
            subtext: 'This item will be marked as Rejected and returned for revision.',
            isProcessing: false
        }
    }

    return {
        allItems, isLoading, isRefreshing, searchQuery, statusFilter,
        currentPage, itemsPerPage, filteredItems, paginatedItems, totalPages,
        nextPage, prevPage,
        isEditModalOpen, isSaving, editForm,
        fetchData, openEdit, handleFileChange, saveEdit,
        getStatusBadge, formatDate, resetFilters,
        confirmModal, requestArchive, executeArchive,
        selectedItem, viewDetails, closeDetails,
        approveResearch, rejectResearch
    }
}
