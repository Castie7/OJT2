import { ref } from 'vue'
import api from '../services/api'

export interface ActivityLog {
    id: number
    user_name: string
    role: string
    action: string
    details: string
    ip_address: string
    created_at: string
}

export function useAdminLogs() {
    const logs = ref<ActivityLog[]>([])
    const loading = ref(false)
    const pagination = ref({
        currentPage: 1,
        totalPages: 1,
        perPage: 20
    })

    // Filter State
    const filters = ref({
        action: 'ALL',
        startDate: '',
        endDate: '',
        search: ''
    })

    // Fetch logs from DB
    const fetchLogs = async (page = 1) => {
        loading.value = true
        try {
            const params = {
                page,
                limit: pagination.value.perPage,
                search: filters.value.search,
                action: filters.value.action !== 'ALL' ? filters.value.action : undefined,
                start_date: filters.value.startDate || undefined,
                end_date: filters.value.endDate || undefined
            }

            const response = await api.get('/api/logs', { params })

            logs.value = response.data.data

            if (response.data.pager) {
                pagination.value.currentPage = response.data.pager.currentPage
                pagination.value.totalPages = response.data.pager.pageCount
            }
        } catch (error) {
            console.error("Failed to fetch activity logs", error)
            logs.value = []
        } finally {
            loading.value = false
        }
    }

    const downloadLogs = () => {
        const params = new URLSearchParams()
        if (filters.value.search) params.append('search', filters.value.search)
        if (filters.value.action !== 'ALL') params.append('action', filters.value.action)
        if (filters.value.startDate) params.append('start_date', filters.value.startDate)
        if (filters.value.endDate) params.append('end_date', filters.value.endDate)

        // Explicitly use FULL URL for window.open to ensure it hits backend
        const baseUrl = api.defaults.baseURL || ''
        const url = `${baseUrl}/api/logs/export?${params.toString()}`

        window.open(url, '_blank')
    }

    const formatActionColor = (action: string) => {
        switch (action) {
            case 'LOGIN': return 'bg-green-100 text-green-800'
            case 'LOGOUT': return 'bg-gray-100 text-gray-800'
            case 'CREATE_RESEARCH': return 'bg-blue-100 text-blue-800'
            case 'UPDATE_PROFILE': return 'bg-purple-100 text-purple-800'
            case 'REJECT_RESEARCH': return 'bg-red-100 text-red-800'
            case 'ARCHIVE_RESEARCH': return 'bg-yellow-100 text-yellow-800'
            case 'APPROVE_RESEARCH': return 'bg-green-100 text-green-800'
            default: return 'bg-gray-100 text-gray-800'
        }
    }

    return {
        logs,
        loading,
        pagination,
        filters,
        fetchLogs,
        downloadLogs,
        formatActionColor
    }
}
