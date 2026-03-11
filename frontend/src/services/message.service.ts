import api from './api'
import type {
  User,
  DirectMessage,
  MessageConversation,
  SendMessageRequest,
  MarkMessagesReadRequest,
  ApiResponse
} from '../types'

export const messageService = {
  async getUsers(): Promise<User[]> {
    const response = await api.get<User[]>('/api/messages/users')
    return response.data
  },

  async getConversations(): Promise<MessageConversation[]> {
    const response = await api.get<MessageConversation[]>('/api/messages/conversations')
    return response.data
  },

  async getThread(partnerId: number, limit = 100): Promise<DirectMessage[]> {
    const response = await api.get<DirectMessage[]>(`/api/messages/thread/${partnerId}`, {
      params: { limit }
    })
    return response.data
  },

  async send(payload: SendMessageRequest): Promise<ApiResponse<DirectMessage>> {
    const response = await api.post<ApiResponse<DirectMessage>>('/api/messages/send', payload)
    return response.data
  },

  async markAsRead(payload: MarkMessagesReadRequest): Promise<ApiResponse<void>> {
    const response = await api.post<ApiResponse<void>>('/api/messages/read', payload)
    return response.data
  },

  async markAllAsRead(): Promise<ApiResponse<void>> {
    const response = await api.post<ApiResponse<void>>('/api/messages/read-all')
    return response.data
  }
}
