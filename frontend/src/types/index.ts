// src/types/index.ts
// Canonical type definitions — import from here instead of redefining locally.

// ============================================================================
// CORE DOMAIN TYPES
// ============================================================================

export interface User {
  id: number
  name: string
  role: string
  email?: string
  created_at?: string
}

export interface Research {
  id: number
  title: string
  author: string
  abstract?: string
  status: 'pending' | 'approved' | 'rejected' | 'archived'
  access_level?: 'public' | 'private'
  file_path?: string
  crop_variation?: string

  // Dates
  start_date?: string
  deadline_date?: string
  created_at?: string
  updated_at?: string
  approved_at?: string
  archived_at?: string
  rejected_at?: string

  // Library Catalog Fields
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

export interface Comment {
  id: number
  user_name: string
  role: string
  comment: string
  created_at?: string
}

export interface Stat {
  id?: string
  title: string
  value: number | string
  color: string
  action?: string
}

export interface ActivityLog {
  id: number
  user_name: string
  role: string
  action: string
  details: string
  ip_address: string
  created_at: string
}

export interface Notification {
  id: number
  type: string
  message: string
  research_id?: number
  created_at: string
  is_read: boolean
}

// ============================================================================
// API RESPONSE TYPES
// ============================================================================

export interface ApiResponse<T = any> {
  status: 'success' | 'error'
  message?: string
  data?: T
  messages?: ValidationErrors
}

export interface ValidationErrors {
  [field: string]: string
}

export interface PaginatedResponse<T> {
  data: T[]
  pager: {
    currentPage: number
    pageCount: number
    perPage: number
    total: number
  }
}

// ============================================================================
// AUTH API TYPES
// ============================================================================

export interface LoginRequest {
  email: string
  password: string
}

export interface LoginResponse {
  status: 'success' | 'error'
  message: string
  user?: User
  csrf_token?: string
}

export interface VerifyResponse {
  authenticated: boolean
  user?: User
  csrf_token?: string
}

export interface RegisterRequest {
  name: string
  email: string
  password: string
  role: 'admin' | 'user'
}

// ============================================================================
// RESEARCH API TYPES
// ============================================================================

export interface ResearchFilters {
  start_date?: string
  end_date?: string
  knowledge_type?: string
  search?: string
}

export interface CreateResearchRequest {
  title: string
  author: string
  crop_variation?: string
  start_date?: string
  deadline_date?: string
  knowledge_type: string
  publication_date?: string
  edition?: string
  publisher?: string
  physical_description?: string
  isbn_issn?: string
  subjects?: string
  shelf_location?: string
  item_condition?: string
  link?: string
  access_level?: 'public' | 'private'
  pdf_file?: File
}

export interface UpdateResearchRequest extends CreateResearchRequest {
  id: number
}

// ============================================================================
// ADMIN API TYPES
// ============================================================================

export interface LogFilters {
  page?: number
  limit?: number
  search?: string
  action?: string
  start_date?: string
  end_date?: string
}

export interface ResetPasswordRequest {
  user_id: number
  new_password: string
}

// ============================================================================
// COMMENT API TYPES
// ============================================================================

export interface CreateCommentRequest {
  research_id: number
  user_id: number
  user_name: string
  role: string
  comment: string
}

// ============================================================================
// DASHBOARD API TYPES
// ============================================================================

export interface DashboardStats {
  total: number
  pending: number
  approved: number
  rejected: number
}
