import api, { getBaseUrl } from './api'
import type {
  WorkspaceStorageSummary,
  WorkspaceStorageFile,
  WorkspaceStorageFolder,
  WorkspaceStorageRecycleSummary
} from '../types'

interface StorageSummaryResponse {
  status: 'success' | 'error'
  data: WorkspaceStorageSummary
  message?: string
}

interface UploadResponse {
  status: 'success' | 'error'
  message?: string
  file: WorkspaceStorageFile
  storage: WorkspaceStorageSummary
}

interface DeleteResponse {
  status: 'success' | 'error'
  message?: string
  deleted_item_id: number
  storage: WorkspaceStorageSummary
}

interface CreateFolderResponse {
  status: 'success' | 'error'
  message?: string
  folder: WorkspaceStorageFolder
  storage: WorkspaceStorageSummary
}

interface MoveResponse {
  status: 'success' | 'error'
  message?: string
  moved_item_id: number
  storage: WorkspaceStorageSummary
}

interface CopyResponse {
  status: 'success' | 'error'
  message?: string
  copied_item_id: number
  storage: WorkspaceStorageSummary
}

interface RecycleBinResponse {
  status: 'success' | 'error'
  data: WorkspaceStorageRecycleSummary
  message?: string
}

interface RestoreResponse {
  status: 'success' | 'error'
  message?: string
  restored_item_id: number
  storage: WorkspaceStorageSummary
  recycle_bin: WorkspaceStorageRecycleSummary
}

interface PermanentDeleteResponse {
  status: 'success' | 'error'
  message?: string
  deleted_item_id: number
  storage: WorkspaceStorageSummary
  recycle_bin: WorkspaceStorageRecycleSummary
}

export const storageService = {
  getOpenUrl(fileId: number): string {
    const baseUrl = String(api.defaults.baseURL ?? getBaseUrl()).replace(/\/+$/, '')
    return `${baseUrl}/api/storage/open/${fileId}`
  },

  open(fileId: number): void {
    const url = this.getOpenUrl(fileId)
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.target = '_self'
    document.body.appendChild(anchor)
    anchor.click()
    anchor.remove()
  },

  async getSummary(path = '/'): Promise<WorkspaceStorageSummary> {
    const response = await api.get<StorageSummaryResponse>('/api/storage', {
      params: { path }
    })
    return response.data.data
  },

  async upload(file: File, folderPath = '/'): Promise<UploadResponse> {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('folder_path', folderPath)

    const response = await api.post<UploadResponse>('/api/storage/upload', formData)
    return response.data
  },

  async createFolder(name: string, path = '/'): Promise<CreateFolderResponse> {
    const response = await api.post<CreateFolderResponse>('/api/storage/folders', {
      name,
      path
    })
    return response.data
  },

  async remove(itemId: number, path = '/'): Promise<DeleteResponse> {
    const response = await api.post<DeleteResponse>(`/api/storage/delete/${itemId}`, {
      path
    })
    return response.data
  },

  async move(itemId: number, targetPath: string, path = '/'): Promise<MoveResponse> {
    const response = await api.post<MoveResponse>(`/api/storage/move/${itemId}`, {
      target_path: targetPath,
      path
    })
    return response.data
  },

  async copy(itemId: number, targetPath: string, path = '/'): Promise<CopyResponse> {
    const response = await api.post<CopyResponse>(`/api/storage/copy/${itemId}`, {
      target_path: targetPath,
      path
    })
    return response.data
  },

  async getRecycleBin(): Promise<WorkspaceStorageRecycleSummary> {
    const response = await api.get<RecycleBinResponse>('/api/storage/recycle-bin')
    return response.data.data
  },

  async restore(itemId: number, path = '/'): Promise<RestoreResponse> {
    const response = await api.post<RestoreResponse>(`/api/storage/restore/${itemId}`, {
      path
    })
    return response.data
  },

  async permanentDelete(itemId: number, path = '/'): Promise<PermanentDeleteResponse> {
    const response = await api.post<PermanentDeleteResponse>(`/api/storage/permanent-delete/${itemId}`, {
      path
    })
    return response.data
  },

  async download(fileId: number, fileName: string): Promise<void> {
    const response = await api.get<Blob>(`/api/storage/download/${fileId}`, {
      responseType: 'blob'
    })

    const objectUrl = window.URL.createObjectURL(response.data)
    const anchor = document.createElement('a')
    anchor.href = objectUrl
    anchor.download = fileName
    document.body.appendChild(anchor)
    anchor.click()
    anchor.remove()
    window.URL.revokeObjectURL(objectUrl)
  }
}
