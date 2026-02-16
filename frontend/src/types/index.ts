// src/types/index.ts
// Canonical type definitions — import from here instead of redefining locally.

export interface User {
  id: number
  name: string
  role: string
  email?: string
}

export interface Research {
  id: number
  title: string
  author: string
  abstract?: string
  status: 'pending' | 'approved' | 'rejected' | 'archived'
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
