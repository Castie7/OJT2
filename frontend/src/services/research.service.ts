// src/services/research.service.ts

import api from './api'
import type {
  Research,
  ResearchFilters,
  ApiResponse,
  Comment
} from '../types'

/**
 * Research Service
 * Handles all research-related API operations
 */
export const researchService = {
  /**
   * Get all approved research items (for library/public view)
   */
  async getAll(filters?: ResearchFilters): Promise<Research[]> {
    const params = new URLSearchParams()
    if (filters?.start_date) params.append('start_date', filters.start_date)
    if (filters?.end_date) params.append('end_date', filters.end_date)

    const queryString = params.toString()
    const endpoint = queryString ? `/research?${queryString}` : '/research'

    const response = await api.get<Research[]>(endpoint)
    return response.data
  },

  /**
   * Get all research items for admin masterlist (includes all statuses)
   */
  async getMasterlist(): Promise<Research[]> {
    const response = await api.get<Research[]>('/research/masterlist')
    return response.data
  },

  /**
   * Get archived research items
   */
  async getArchived(filters?: ResearchFilters): Promise<Research[]> {
    const params = new URLSearchParams()
    if (filters?.start_date) params.append('start_date', filters.start_date)
    if (filters?.end_date) params.append('end_date', filters.end_date)

    const queryString = params.toString()
    const endpoint = queryString ? `/research/archived?${queryString}` : '/research/archived'

    const response = await api.get<Research[]>(endpoint)
    return response.data
  },

  /**
   * Get pending research submissions (admin only)
   */
  async getPending(): Promise<Research[]> {
    const response = await api.get<Research[]>('/research/pending')
    return response.data
  },

  /**
   * Get rejected research submissions (admin only)
   */
  async getRejected(): Promise<Research[]> {
    const response = await api.get<Research[]>('/research/rejected')
    return response.data
  },

  /**
   * Get current user's research submissions
   */
  async getMySubmissions(): Promise<Research[]> {
    const response = await api.get<Research[]>('/research/my-submissions')
    return response.data
  },

  /**
   * Get current user's archived submissions
   */
  async getMyArchived(): Promise<Research[]> {
    const response = await api.get<Research[]>('/research/my-archived')
    return response.data
  },

  /**
   * Create new research submission
   */
  async create(data: FormData): Promise<ApiResponse<Research>> {
    const response = await api.post<ApiResponse<Research>>('/research/create', data)
    return response.data
  },

  /**
   * Update existing research submission
   */
  async update(id: number, data: FormData): Promise<ApiResponse<Research>> {
    const response = await api.post<ApiResponse<Research>>(`/research/update/${id}`, data)
    return response.data
  },

  /**
   * Approve research submission (admin only)
   */
  async approve(id: number): Promise<ApiResponse<void>> {
    const response = await api.post<ApiResponse<void>>(`/research/approve/${id}`)
    return response.data
  },

  /**
   * Reject research submission (admin only)
   */
  async reject(id: number): Promise<ApiResponse<void>> {
    const response = await api.post<ApiResponse<void>>(`/research/reject/${id}`)
    return response.data
  },

  /**
   * Restore rejected research to pending (admin only)
   */
  async restore(id: number): Promise<ApiResponse<void>> {
    const response = await api.post<ApiResponse<void>>(`/research/restore/${id}`)
    return response.data
  },

  /**
   * Archive research item
   */
  async archive(id: number): Promise<ApiResponse<void>> {
    const response = await api.post<ApiResponse<void>>(`/research/archive/${id}`)
    return response.data
  },

  /**
   * Extend deadline for research submission (admin only)
   */
  async extendDeadline(id: number, newDeadline: string): Promise<ApiResponse<void>> {
    const formData = new FormData()
    formData.append('new_deadline', newDeadline)

    const response = await api.post<ApiResponse<void>>(`/research/extend-deadline/${id}`, formData)
    return response.data
  },

  /**
   * Get comments for a research item
   */
  async getComments(id: number): Promise<Comment[]> {
    const response = await api.get<Comment[]>(`/research/comments/${id}`)
    return response.data
  }
}
