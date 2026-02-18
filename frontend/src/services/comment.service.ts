// src/services/comment.service.ts

import api from './api'
import type { CreateCommentRequest, Comment, ApiResponse } from '../types'

/**
 * Comment Service
 * Handles comment operations
 */
export const commentService = {
  /**
   * Create a new comment on a research item
   */
  async create(data: CreateCommentRequest): Promise<ApiResponse<Comment>> {
    const response = await api.post<ApiResponse<Comment>>('/api/comments', data)
    return response.data
  }
}
