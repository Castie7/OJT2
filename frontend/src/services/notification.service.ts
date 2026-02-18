// src/services/notification.service.ts

import api from './api'
import type { Notification, ApiResponse } from '../types'

/**
 * Notification Service
 * Handles notification operations
 */
export const notificationService = {
  /**
   * Get all notifications for current user
   */
  async getAll(userId: number): Promise<Notification[]> {
    const response = await api.get<Notification[]>(`/api/notifications?user_id=${userId}`)
    return response.data
  },

  /**
   * Mark all notifications as read for a user
   */
  async markAllAsRead(userId: number): Promise<ApiResponse<void>> {
    const response = await api.post<ApiResponse<void>>('/api/notifications/read', { user_id: userId })
    return response.data
  }
}
