import { computed, ref } from 'vue'
import { storageService } from '../services'
import { useErrorHandler } from './useErrorHandler'
import { useToast } from './useToast'
import type {
  WorkspaceStorageFile,
  WorkspaceStorageFolder,
  WorkspaceStorageSummary,
  WorkspaceStorageRecycleItem,
  WorkspaceStorageRecycleSummary
} from '../types'

const DEFAULT_SUMMARY: WorkspaceStorageSummary = {
  quota_bytes: 1073741824,
  used_bytes: 0,
  remaining_bytes: 1073741824,
  usage_percent: 0,
  current_path: '/',
  parent_path: null,
  folders: [],
  files: []
}

const DEFAULT_RECYCLE_SUMMARY: WorkspaceStorageRecycleSummary = {
  retention_days: 30,
  item_count: 0,
  items: []
}

export function useWorkspaceStorage() {
  const summary = ref<WorkspaceStorageSummary>({ ...DEFAULT_SUMMARY })
  const selectedFile = ref<File | null>(null)
  const isLoading = ref(false)
  const isRecycleLoading = ref(false)
  const isUploading = ref(false)
  const activeFileActionId = ref<number | null>(null)
  const recycleSummary = ref<WorkspaceStorageRecycleSummary>({ ...DEFAULT_RECYCLE_SUMMARY })

  const { handleError } = useErrorHandler()
  const { showToast } = useToast()

  const files = computed(() => summary.value.files ?? [])
  const folders = computed(() => summary.value.folders ?? [])
  const recycleItems = computed<WorkspaceStorageRecycleItem[]>(() => recycleSummary.value.items ?? [])
  const recycleRetentionDays = computed(() => recycleSummary.value.retention_days ?? 30)
  const currentPath = computed(() => summary.value.current_path ?? '/')
  const parentPath = computed(() => summary.value.parent_path ?? null)
  const quotaBytes = computed(() => summary.value.quota_bytes ?? DEFAULT_SUMMARY.quota_bytes)
  const usedBytes = computed(() => summary.value.used_bytes ?? 0)
  const remainingBytes = computed(() => summary.value.remaining_bytes ?? quotaBytes.value)
  const usagePercent = computed(() => {
    const raw = summary.value.usage_percent
    const normalized = Number.isFinite(raw) ? raw : 0
    return Math.max(0, Math.min(100, normalized))
  })

  const canUpload = computed(() => selectedFile.value !== null && !isUploading.value)

  const setRecycleSummary = (nextSummary: WorkspaceStorageRecycleSummary) => {
    recycleSummary.value = {
      retention_days: nextSummary.retention_days ?? 30,
      item_count: nextSummary.item_count ?? 0,
      items: Array.isArray(nextSummary.items) ? nextSummary.items : []
    }
  }

  const setSummary = (nextSummary: WorkspaceStorageSummary) => {
    summary.value = {
      quota_bytes: nextSummary.quota_bytes ?? DEFAULT_SUMMARY.quota_bytes,
      used_bytes: nextSummary.used_bytes ?? 0,
      remaining_bytes: nextSummary.remaining_bytes ?? 0,
      usage_percent: nextSummary.usage_percent ?? 0,
      current_path: nextSummary.current_path ?? '/',
      parent_path: nextSummary.parent_path ?? null,
      folders: Array.isArray(nextSummary.folders) ? nextSummary.folders : [],
      files: Array.isArray(nextSummary.files) ? nextSummary.files : []
    }
  }

  const fetchSummary = async (path = currentPath.value) => {
    isLoading.value = true
    try {
      const response = await storageService.getSummary(path)
      setSummary(response)
    } catch (error) {
      handleError(error, 'Failed to load workspace storage.')
    } finally {
      isLoading.value = false
    }
  }

  const fetchRecycleBin = async () => {
    isRecycleLoading.value = true
    try {
      const response = await storageService.getRecycleBin()
      setRecycleSummary(response)
    } catch (error) {
      handleError(error, 'Failed to load recycle bin.')
    } finally {
      isRecycleLoading.value = false
    }
  }

  const handleFileSelection = (event: Event) => {
    const target = event.target as HTMLInputElement
    const file = target.files?.[0] ?? null
    setSelectedFile(file)
  }

  const setSelectedFile = (file: File | null) => {
    selectedFile.value = file
  }

  const clearSelectedFile = () => {
    selectedFile.value = null
  }

  const uploadSelectedFile = async () => {
    if (!selectedFile.value || isUploading.value) {
      return
    }

    const chosenFile = selectedFile.value
    if (chosenFile.size > remainingBytes.value) {
      showToast('Not enough remaining storage for this file.', 'error')
      return
    }

    isUploading.value = true
    try {
      const response = await storageService.upload(chosenFile, currentPath.value)
      setSummary(response.storage)
      selectedFile.value = null
      showToast('File uploaded successfully.', 'success')
    } catch (error) {
      handleError(error, 'Failed to upload file.')
    } finally {
      isUploading.value = false
    }
  }

  const downloadFile = async (file: WorkspaceStorageFile) => {
    if (activeFileActionId.value !== null) {
      return
    }

    activeFileActionId.value = file.id
    try {
      await storageService.download(file.id, file.original_name)
    } catch (error) {
      handleError(error, 'Failed to download file.')
    } finally {
      activeFileActionId.value = null
    }
  }

  const openFile = (file: WorkspaceStorageFile) => {
    if (activeFileActionId.value !== null) {
      return
    }

    activeFileActionId.value = file.id
    try {
      storageService.open(file.id)
    } catch (error) {
      handleError(error, 'Failed to open file.')
    } finally {
      activeFileActionId.value = null
    }
  }

  const createFolder = async (folderName: string): Promise<boolean> => {
    const cleanName = folderName.trim()
    if (!cleanName) {
      showToast('Folder name is required.', 'error')
      return false
    }

    if (activeFileActionId.value !== null) {
      return false
    }

    activeFileActionId.value = -1
    try {
      const response = await storageService.createFolder(cleanName, currentPath.value)
      setSummary(response.storage)
      showToast('Folder created successfully.', 'success')
      return true
    } catch (error) {
      handleError(error, 'Failed to create folder.')
      return false
    } finally {
      activeFileActionId.value = null
    }
  }

  const navigateToFolder = async (path: string) => {
    await fetchSummary(path)
  }

  const navigateToParentFolder = async () => {
    if (!parentPath.value) {
      return
    }

    await fetchSummary(parentPath.value)
  }

  const deleteFile = async (item: WorkspaceStorageFile | WorkspaceStorageFolder): Promise<boolean> => {
    if (activeFileActionId.value !== null) {
      return false
    }

    activeFileActionId.value = item.id
    try {
      const response = await storageService.remove(item.id, currentPath.value)
      setSummary(response.storage)
      showToast('Item moved to recycle bin.', 'success')
      return true
    } catch (error) {
      handleError(error, 'Failed to delete item.')
      return false
    } finally {
      activeFileActionId.value = null
    }
  }

  const moveItem = async (
    item: WorkspaceStorageFile | WorkspaceStorageFolder,
    targetPath: string
  ): Promise<boolean> => {
    const cleanTargetPath = String(targetPath || '/').trim() || '/'

    if (activeFileActionId.value !== null) {
      return false
    }

    if (item.folder_path === cleanTargetPath) {
      showToast('Item is already in that folder.', 'info')
      return false
    }

    activeFileActionId.value = item.id
    try {
      const response = await storageService.move(item.id, cleanTargetPath, currentPath.value)
      setSummary(response.storage)
      showToast('Item moved.', 'success')
      return true
    } catch (error) {
      handleError(error, 'Failed to move item.')
      return false
    } finally {
      activeFileActionId.value = null
    }
  }

  const copyItem = async (
    item: WorkspaceStorageFile | WorkspaceStorageFolder,
    targetPath: string
  ): Promise<boolean> => {
    const cleanTargetPath = String(targetPath || '/').trim() || '/'

    if (activeFileActionId.value !== null) {
      return false
    }

    activeFileActionId.value = item.id
    try {
      const response = await storageService.copy(item.id, cleanTargetPath, currentPath.value)
      setSummary(response.storage)
      showToast('Item copied.', 'success')
      return true
    } catch (error) {
      handleError(error, 'Failed to copy item.')
      return false
    } finally {
      activeFileActionId.value = null
    }
  }

  const restoreFromRecycleBin = async (item: WorkspaceStorageRecycleItem): Promise<boolean> => {
    if (activeFileActionId.value !== null) {
      return false
    }

    activeFileActionId.value = item.id
    try {
      const response = await storageService.restore(item.id, currentPath.value)
      setSummary(response.storage)
      setRecycleSummary(response.recycle_bin)
      showToast('Item restored.', 'success')
      return true
    } catch (error) {
      handleError(error, 'Failed to restore item.')
      return false
    } finally {
      activeFileActionId.value = null
    }
  }

  const permanentlyDeleteFromRecycleBin = async (item: WorkspaceStorageRecycleItem): Promise<boolean> => {
    if (activeFileActionId.value !== null) {
      return false
    }

    activeFileActionId.value = item.id
    try {
      const response = await storageService.permanentDelete(item.id, currentPath.value)
      setSummary(response.storage)
      setRecycleSummary(response.recycle_bin)
      showToast('Item permanently deleted.', 'success')
      return true
    } catch (error) {
      handleError(error, 'Failed to permanently delete item.')
      return false
    } finally {
      activeFileActionId.value = null
    }
  }

  const formatBytes = (bytes: number): string => {
    if (!Number.isFinite(bytes) || bytes < 0) {
      return '0 B'
    }

    if (bytes < 1024) {
      return `${bytes} B`
    }

    const units = ['KB', 'MB', 'GB', 'TB']
    let value = bytes / 1024
    let index = 0

    while (value >= 1024 && index < units.length - 1) {
      value /= 1024
      index++
    }

    return `${value.toFixed(2)} ${units[index]}`
  }

  const formatDateTime = (value: string): string => {
    if (!value) {
      return 'Unknown'
    }

    const date = new Date(value)
    if (Number.isNaN(date.getTime())) {
      return 'Unknown'
    }

    return date.toLocaleString()
  }

  return {
    summary,
    files,
    folders,
    recycleItems,
    recycleRetentionDays,
    currentPath,
    parentPath,
    selectedFile,
    isLoading,
    isRecycleLoading,
    isUploading,
    activeFileActionId,
    quotaBytes,
    usedBytes,
    remainingBytes,
    usagePercent,
    canUpload,
    fetchSummary,
    fetchRecycleBin,
    navigateToFolder,
    navigateToParentFolder,
    handleFileSelection,
    setSelectedFile,
    clearSelectedFile,
    uploadSelectedFile,
    createFolder,
    openFile,
    downloadFile,
    deleteFile,
    moveItem,
    copyItem,
    restoreFromRecycleBin,
    permanentlyDeleteFromRecycleBin,
    formatBytes,
    formatDateTime
  }
}
