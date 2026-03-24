<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import BaseButton from '../../ui/BaseButton.vue'
import BaseCard from '../../ui/BaseCard.vue'
import { useWorkspaceStorage } from '../../../composables/useWorkspaceStorage'
import { storageService } from '../../../services'
import type { WorkspaceStorageFile, WorkspaceStorageFolder, WorkspaceStorageRecycleItem } from '../../../types'

const fileInputRef = ref<HTMLInputElement | null>(null)
const isDragOver = ref(false)
const dragDepth = ref(0)
const draggedItemId = ref<number | null>(null)
const dropTargetFolderId = ref<number | null>(null)

const {
  files,
  folders,
  recycleItems,
  recycleRetentionDays,
  currentPath,
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
  downloadFile,
  deleteFile,
  moveItem,
  copyItem,
  restoreFromRecycleBin,
  permanentlyDeleteFromRecycleBin,
  formatBytes,
  formatDateTime
} = useWorkspaceStorage()

type DriveItem = WorkspaceStorageFile | WorkspaceStorageFolder

const usageBarClass = computed(() => {
  if (usagePercent.value >= 90) return 'bg-red-600'
  if (usagePercent.value >= 70) return 'bg-amber-500'
  return 'bg-emerald-600'
})

const previewFile = ref<WorkspaceStorageFile | null>(null)
const previewSrc = ref('')
const viewMode = ref<'list' | 'thumbnails'>('thumbnails')
const storageView = ref<'drive' | 'recycle'>('drive')

interface RecentOpenedEntry {
  id: number
  original_name: string
  mime_type: string
  opened_at: string
}

interface RecentOpenedViewEntry extends RecentOpenedEntry {
  file: WorkspaceStorageFile | null
  exists: boolean
}

type FileDisplayMeta = Pick<WorkspaceStorageFile, 'original_name' | 'mime_type'>

const RECENT_OPENED_STORAGE_KEY = 'my_drive_recent_opened'
const MAX_RECENT_OPENED = 12

const recentOpened = ref<RecentOpenedEntry[]>([])
const isRecentCollapsed = ref(false)

interface ContextMenuState {
  visible: boolean
  x: number
  y: number
  item: DriveItem | null
}

const contextMenu = ref<ContextMenuState>({
  visible: false,
  x: 0,
  y: 0,
  item: null
})
const isNewFolderModalOpen = ref(false)
const newFolderName = ref('')
const newFolderInputRef = ref<HTMLInputElement | null>(null)
const isSelectionMode = ref(false)
const selectedItemIds = ref<number[]>([])

const isDeleteConfirmModalOpen = ref(false)
const deleteConfirmTitle = ref('Confirm Delete')
const deleteConfirmMessage = ref('')
const isDeleteConfirmSubmitting = ref(false)
const pendingDeleteAction = ref<null | (() => Promise<void>)>(null)

const driveItems = computed<DriveItem[]>(() => {
  const folderItems = [...folders.value].sort((a, b) =>
    a.original_name.localeCompare(b.original_name, undefined, { sensitivity: 'base' })
  )
  const fileItems = [...files.value]
  return [...folderItems, ...fileItems]
})
const recycleCount = computed(() => recycleItems.value.length)
const selectedDriveItems = computed<DriveItem[]>(() => {
  if (selectedItemIds.value.length === 0) {
    return []
  }

  const selectedSet = new Set(selectedItemIds.value)
  return driveItems.value.filter((item) => selectedSet.has(item.id))
})
const selectedCount = computed(() => selectedDriveItems.value.length)

const isDraggingDriveItem = computed(() => draggedItemId.value !== null)

const findDriveItemById = (id: number): DriveItem | null => {
  for (const folder of folders.value) {
    if (folder.id === id) {
      return folder
    }
  }

  for (const file of files.value) {
    if (file.id === id) {
      return file
    }
  }

  return null
}

const isInlinePreviewable = (file: WorkspaceStorageFile): boolean => {
  const mime = (file.mime_type || '').toLowerCase()
  const name = (file.original_name || '').toLowerCase()
  return mime.startsWith('image/') || mime === 'application/pdf' || name.endsWith('.pdf')
}

const isFolder = (item: DriveItem): item is WorkspaceStorageFolder => item.item_type === 'folder'

const isRootPath = computed(() => currentPath.value === '/')
const currentPathLabel = computed(() => (currentPath.value === '/' ? 'Root' : currentPath.value))

const isImagePreview = computed(() => {
  const file = previewFile.value
  if (!file) {
    return false
  }

  const mime = (file.mime_type || '').toLowerCase()
  const name = (file.original_name || '').toLowerCase()
  return mime.startsWith('image/') || /\.(png|jpe?g|gif|webp|bmp|svg)$/i.test(name)
})

const isImageFile = (file: FileDisplayMeta): boolean => {
  const mime = (file.mime_type || '').toLowerCase()
  const name = (file.original_name || '').toLowerCase()
  return mime.startsWith('image/') || /\.(png|jpe?g|gif|webp|bmp|svg)$/i.test(name)
}

type FileIconType = 'image' | 'pdf' | 'word' | 'excel' | 'archive' | 'text' | 'generic'

const getFileIconType = (file: FileDisplayMeta): FileIconType => {
  const mime = (file.mime_type || '').toLowerCase()
  const name = (file.original_name || '').toLowerCase()

  if (isImageFile(file)) {
    return 'image'
  }

  if (mime === 'application/pdf' || name.endsWith('.pdf')) {
    return 'pdf'
  }

  if (
    mime.includes('word')
    || name.endsWith('.doc')
    || name.endsWith('.docx')
    || name.endsWith('.odt')
  ) {
    return 'word'
  }

  if (
    mime.includes('excel')
    || mime.includes('spreadsheet')
    || name.endsWith('.xls')
    || name.endsWith('.xlsx')
    || name.endsWith('.csv')
    || name.endsWith('.ods')
  ) {
    return 'excel'
  }

  if (
    mime.includes('zip')
    || mime.includes('rar')
    || mime.includes('7z')
    || name.endsWith('.zip')
    || name.endsWith('.rar')
    || name.endsWith('.7z')
    || name.endsWith('.tar')
    || name.endsWith('.gz')
  ) {
    return 'archive'
  }

  if (mime.startsWith('text/') || name.endsWith('.txt') || name.endsWith('.md')) {
    return 'text'
  }

  return 'generic'
}

const getFileIconBadgeClass = (file: FileDisplayMeta): string => {
  const iconType = getFileIconType(file)

  switch (iconType) {
    case 'image':
      return 'bg-emerald-100 text-emerald-700 border-emerald-200'
    case 'pdf':
      return 'bg-rose-100 text-rose-700 border-rose-200'
    case 'word':
      return 'bg-blue-100 text-blue-700 border-blue-200'
    case 'excel':
      return 'bg-green-100 text-green-700 border-green-200'
    case 'archive':
      return 'bg-amber-100 text-amber-700 border-amber-200'
    case 'text':
      return 'bg-slate-100 text-slate-700 border-slate-200'
    default:
      return 'bg-gray-100 text-gray-700 border-gray-200'
  }
}

const getFileIconSymbol = (file: FileDisplayMeta): string => {
  const iconType = getFileIconType(file)

  switch (iconType) {
    case 'image':
      return '🖼'
    case 'pdf':
      return '📕'
    case 'word':
      return '📝'
    case 'excel':
      return '📊'
    case 'archive':
      return '🗜'
    case 'text':
      return '📄'
    default:
      return '📁'
  }
}

const getFileExtension = (file: FileDisplayMeta): string => {
  const name = (file.original_name || '').trim()
  const lastDotIndex = name.lastIndexOf('.')
  if (lastDotIndex === -1 || lastDotIndex === name.length - 1) {
    return 'FILE'
  }

  return name.slice(lastDotIndex + 1).toUpperCase()
}

const getThumbnailSource = (file: WorkspaceStorageFile): string => {
  return storageService.getOpenUrl(file.id)
}

const loadRecentOpened = () => {
  try {
    const raw = localStorage.getItem(RECENT_OPENED_STORAGE_KEY)
    if (!raw) {
      recentOpened.value = []
      return
    }

    const parsed = JSON.parse(raw)
    if (!Array.isArray(parsed)) {
      recentOpened.value = []
      return
    }

    const seen = new Set<number>()
    recentOpened.value = parsed
      .filter((entry) => entry && typeof entry.id === 'number')
      .map((entry) => ({
        id: Number(entry.id),
        original_name: String(entry.original_name ?? 'Unknown file'),
        mime_type: String(entry.mime_type ?? 'application/octet-stream'),
        opened_at: String(entry.opened_at ?? '')
      }))
      .filter((entry) => {
        if (seen.has(entry.id)) {
          return false
        }
        seen.add(entry.id)
        return true
      })
      .slice(0, MAX_RECENT_OPENED)
  } catch {
    recentOpened.value = []
  }
}

const saveRecentOpened = () => {
  try {
    localStorage.setItem(RECENT_OPENED_STORAGE_KEY, JSON.stringify(recentOpened.value))
  } catch {
    // Ignore localStorage write errors
  }
}

const rememberOpenedFile = (file: WorkspaceStorageFile) => {
  const entry: RecentOpenedEntry = {
    id: file.id,
    original_name: file.original_name,
    mime_type: file.mime_type,
    opened_at: new Date().toISOString()
  }

  recentOpened.value = [
    entry,
    ...recentOpened.value.filter((item) => item.id !== file.id)
  ].slice(0, MAX_RECENT_OPENED)

  saveRecentOpened()
}

const recentOpenedFiles = computed<RecentOpenedViewEntry[]>(() => {
  const fileMap = new Map(files.value.map((file) => [file.id, file]))

  return recentOpened.value.map((entry) => {
    const matched = fileMap.get(entry.id) ?? null
    return {
      ...entry,
      original_name: matched?.original_name ?? entry.original_name,
      mime_type: matched?.mime_type ?? entry.mime_type,
      exists: matched !== null,
      file: matched
    }
  })
})

const formatRelativeOpenedTime = (value: string): string => {
  if (!value) return 'Unknown time'

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return 'Unknown time'

  const diffMs = Date.now() - date.getTime()
  if (diffMs < 60 * 1000) return 'Just now'
  if (diffMs < 60 * 60 * 1000) return `${Math.floor(diffMs / (60 * 1000))}m ago`
  if (diffMs < 24 * 60 * 60 * 1000) return `${Math.floor(diffMs / (60 * 60 * 1000))}h ago`
  if (diffMs < 7 * 24 * 60 * 60 * 1000) return `${Math.floor(diffMs / (24 * 60 * 60 * 1000))}d ago`

  return date.toLocaleDateString()
}

const openRecentFile = async (entry: RecentOpenedViewEntry) => {
  if (!entry.file) {
    return
  }

  await open(entry.file)
}

const triggerFilePicker = () => {
  if (isUploading.value) {
    return
  }
  fileInputRef.value?.click()
}

const onFileChange = (event: Event) => {
  handleFileSelection(event)
}

const resetSelectedFile = () => {
  clearSelectedFile()
  if (fileInputRef.value) {
    fileInputRef.value.value = ''
  }
}

const upload = async () => {
  await uploadSelectedFile()
  if (!selectedFile.value && fileInputRef.value) {
    fileInputRef.value.value = ''
  }
}

const download = async (file: WorkspaceStorageFile) => {
  await downloadFile(file)
}

const open = async (file: WorkspaceStorageFile) => {
  rememberOpenedFile(file)

  if (isInlinePreviewable(file)) {
    previewFile.value = file
    previewSrc.value = storageService.getOpenUrl(file.id)
    return
  }

  await downloadFile(file)
}

const openItem = async (item: DriveItem) => {
  closeContextMenu()

  if (isFolder(item)) {
    await navigateToFolder(item.full_path)
    return
  }

  await open(item)
}

const isItemSelected = (item: DriveItem): boolean => selectedItemIds.value.includes(item.id)

const clearSelectionMode = () => {
  isSelectionMode.value = false
  selectedItemIds.value = []
}

const enableSelectionMode = (item?: DriveItem) => {
  isSelectionMode.value = true
  if (!item) {
    return
  }

  if (!selectedItemIds.value.includes(item.id)) {
    selectedItemIds.value = [...selectedItemIds.value, item.id]
  }
}

const toggleSelectionForItem = (item: DriveItem) => {
  if (!isSelectionMode.value) {
    return
  }

  const selectedSet = new Set(selectedItemIds.value)
  if (selectedSet.has(item.id)) {
    selectedSet.delete(item.id)
  } else {
    selectedSet.add(item.id)
  }

  selectedItemIds.value = Array.from(selectedSet)
}

const handleItemPrimaryAction = async (item: DriveItem) => {
  if (isSelectionMode.value) {
    toggleSelectionForItem(item)
    return
  }

  await openItem(item)
}

const openDeleteConfirmDialog = (
  title: string,
  message: string,
  action: () => Promise<void>
) => {
  deleteConfirmTitle.value = title
  deleteConfirmMessage.value = message
  pendingDeleteAction.value = action
  isDeleteConfirmSubmitting.value = false
  isDeleteConfirmModalOpen.value = true
}

const closeDeleteConfirmDialog = () => {
  isDeleteConfirmModalOpen.value = false
  deleteConfirmTitle.value = 'Confirm Delete'
  deleteConfirmMessage.value = ''
  pendingDeleteAction.value = null
  isDeleteConfirmSubmitting.value = false
}

const confirmDeleteDialog = async () => {
  if (!pendingDeleteAction.value || isDeleteConfirmSubmitting.value) {
    return
  }

  isDeleteConfirmSubmitting.value = true
  try {
    await pendingDeleteAction.value()
  } finally {
    closeDeleteConfirmDialog()
  }
}

const requestDeleteItem = (item: DriveItem) => {
  openDeleteConfirmDialog(
    'Move To Recycle Bin',
    `Move "${item.original_name}" to Recycle Bin?`,
    async () => {
      const deleted = await deleteFile(item)
      if (deleted) {
        await fetchRecycleBin()
      }
    }
  )
}

const requestDeleteSelectedItems = () => {
  if (selectedCount.value === 0) {
    return
  }

  openDeleteConfirmDialog(
    'Move Selected Items',
    `Move ${selectedCount.value} selected item(s) to Recycle Bin?`,
    async () => {
      const itemsToDelete = [...selectedDriveItems.value]
      let anyDeleted = false

      for (const item of itemsToDelete) {
        const deleted = await deleteFile(item)
        if (deleted) {
          anyDeleted = true
        }
      }

      if (anyDeleted) {
        await fetchRecycleBin()
      }

      clearSelectionMode()
    }
  )
}

const openNewFolderModal = async () => {
  closeContextMenu()
  isNewFolderModalOpen.value = true
  newFolderName.value = ''
  await nextTick()
  newFolderInputRef.value?.focus()
}

const closeNewFolderModal = () => {
  isNewFolderModalOpen.value = false
  newFolderName.value = ''
}

const submitNewFolder = async () => {
  const created = await createFolder(newFolderName.value)
  if (created) {
    closeNewFolderModal()
  }
}

const closeContextMenu = () => {
  contextMenu.value.visible = false
  contextMenu.value.item = null
}

const openItemContextMenu = (event: MouseEvent, item: DriveItem) => {
  if (storageView.value !== 'drive') {
    return
  }

  event.preventDefault()
  contextMenu.value = {
    visible: true,
    x: event.clientX,
    y: event.clientY,
    item
  }
}

const openBackgroundContextMenu = (event: MouseEvent) => {
  if (storageView.value !== 'drive') {
    return
  }

  event.preventDefault()
  contextMenu.value = {
    visible: true,
    x: event.clientX,
    y: event.clientY,
    item: null
  }
}

const openFromContextMenu = async () => {
  if (!contextMenu.value.item) {
    return
  }

  await openItem(contextMenu.value.item)
}

const downloadFromContextMenu = async () => {
  if (!contextMenu.value.item || isFolder(contextMenu.value.item)) {
    return
  }

  const selected = contextMenu.value.item
  closeContextMenu()
  await download(selected)
}

const selectFromContextMenu = () => {
  if (!contextMenu.value.item) {
    return
  }

  enableSelectionMode(contextMenu.value.item)
  closeContextMenu()
}

const deleteFromContextMenu = () => {
  if (!contextMenu.value.item) {
    return
  }

  const item = contextMenu.value.item
  closeContextMenu()
  requestDeleteItem(item)
}

const moveSelectedToContextFolder = async () => {
  const target = contextMenu.value.item
  if (!target || !isFolder(target) || selectedCount.value === 0) {
    return
  }

  closeContextMenu()
  const itemsToMove = [...selectedDriveItems.value]
  for (const item of itemsToMove) {
    await moveItem(item, target.full_path)
  }
  clearSelectionMode()
}

const copySelectedToContextFolder = async () => {
  const target = contextMenu.value.item
  if (!target || !isFolder(target) || selectedCount.value === 0) {
    return
  }

  closeContextMenu()
  const itemsToCopy = [...selectedDriveItems.value]
  for (const item of itemsToCopy) {
    await copyItem(item, target.full_path)
  }
}

const deleteSelectedFromContextMenu = () => {
  closeContextMenu()
  requestDeleteSelectedItems()
}

const navigateUp = async () => {
  await navigateToParentFolder()
}

const navigateRoot = async () => {
  if (isRootPath.value) {
    return
  }

  await navigateToFolder('/')
}

const formatRecycleLocation = (item: WorkspaceStorageRecycleItem): string => {
  if (item.item_type === 'folder') {
    return item.folder_path === '/' ? '/' : item.folder_path
  }

  return item.folder_path || '/'
}

const restoreRecycleItem = async (item: WorkspaceStorageRecycleItem) => {
  const restored = await restoreFromRecycleBin(item)
  if (restored) {
    await fetchRecycleBin()
  }
}

const permanentlyDeleteRecycleItem = async (item: WorkspaceStorageRecycleItem) => {
  openDeleteConfirmDialog(
    'Permanent Delete',
    `Permanently delete "${item.original_name}"? This cannot be undone.`,
    async () => {
      const deleted = await permanentlyDeleteFromRecycleBin(item)
      if (deleted) {
        await fetchRecycleBin()
      }
    }
  )
}

const handleGlobalClick = () => {
  closeContextMenu()
}

const handleEscapeKey = (event: KeyboardEvent) => {
  if (event.key === 'Escape') {
    closeDeleteConfirmDialog()
    closeNewFolderModal()
    closeContextMenu()
  }
}

const closePreview = () => {
  previewFile.value = null
  previewSrc.value = ''
}

const downloadPreviewFile = async () => {
  if (!previewFile.value) {
    return
  }

  await download(previewFile.value)
}

const hasExternalFiles = (event: DragEvent): boolean => {
  const dragTypes = event.dataTransfer?.types
  if (!dragTypes) {
    return false
  }

  return Array.from(dragTypes).includes('Files')
}

const onItemDragStart = (event: DragEvent, item: DriveItem) => {
  if (isFolder(item) || isUploading.value || activeFileActionId.value !== null) {
    event.preventDefault()
    return
  }

  draggedItemId.value = item.id
  dropTargetFolderId.value = null

  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('text/plain', String(item.id))
  }
}

const onItemDragEnd = () => {
  draggedItemId.value = null
  dropTargetFolderId.value = null
}

const onFolderDragOver = (event: DragEvent, item: DriveItem) => {
  if (!isFolder(item) || draggedItemId.value === null) {
    return
  }

  const draggedItem = findDriveItemById(draggedItemId.value)
  if (!draggedItem || isFolder(draggedItem)) {
    return
  }

  if (draggedItem.folder_path === item.full_path) {
    return
  }

  event.preventDefault()
  event.stopPropagation()
  dropTargetFolderId.value = item.id

  if (event.dataTransfer) {
    event.dataTransfer.dropEffect = 'move'
  }
}

const onFolderDragLeave = (_event: DragEvent, item: DriveItem) => {
  if (!isFolder(item)) {
    return
  }

  if (dropTargetFolderId.value === item.id) {
    dropTargetFolderId.value = null
  }
}

const onFolderDrop = async (event: DragEvent, item: DriveItem) => {
  if (!isFolder(item)) {
    return
  }

  event.preventDefault()
  event.stopPropagation()

  const draggedId = draggedItemId.value
  draggedItemId.value = null
  dropTargetFolderId.value = null

  if (draggedId === null || isUploading.value || activeFileActionId.value !== null) {
    return
  }

  const draggedItem = findDriveItemById(draggedId)
  if (!draggedItem || isFolder(draggedItem)) {
    return
  }

  if (draggedItem.folder_path === item.full_path) {
    return
  }

  await moveItem(draggedItem, item.full_path)
}

const onDragEnter = (event: DragEvent) => {
  event.preventDefault()
  if (isDraggingDriveItem.value || isUploading.value || !hasExternalFiles(event)) {
    return
  }

  dragDepth.value += 1
  isDragOver.value = true
}

const onDragOver = (event: DragEvent) => {
  event.preventDefault()
  if (isDraggingDriveItem.value || isUploading.value || !hasExternalFiles(event)) {
    return
  }
  isDragOver.value = true
}

const onDragLeave = (event: DragEvent) => {
  event.preventDefault()
  if (isDraggingDriveItem.value || isUploading.value || !hasExternalFiles(event)) {
    return
  }

  dragDepth.value = Math.max(0, dragDepth.value - 1)
  if (dragDepth.value === 0) {
    isDragOver.value = false
  }
}

const onDrop = (event: DragEvent) => {
  event.preventDefault()
  dragDepth.value = 0
  isDragOver.value = false

  if (isDraggingDriveItem.value || isUploading.value || !hasExternalFiles(event)) {
    return
  }

  const file = event.dataTransfer?.files?.[0] ?? null
  setSelectedFile(file)
}

watch(currentPath, () => {
  clearSelectionMode()
})

watch(storageView, (nextView) => {
  closeContextMenu()
  if (nextView !== 'drive') {
    clearSelectionMode()
  }
})

onMounted(() => {
  loadRecentOpened()
  fetchSummary()
  fetchRecycleBin()
  window.addEventListener('click', handleGlobalClick)
  window.addEventListener('keydown', handleEscapeKey)
})

onBeforeUnmount(() => {
  window.removeEventListener('click', handleGlobalClick)
  window.removeEventListener('keydown', handleEscapeKey)
})
</script>

<template>
  <BaseCard>
    <div
      class="space-y-5 relative rounded-2xl border border-gray-100 bg-white p-4 md:p-5 transition-colors"
      :class="isDragOver ? 'bg-emerald-50/70 ring-2 ring-emerald-300 ring-inset' : ''"
      @dragenter="onDragEnter"
      @dragover="onDragOver"
      @dragleave="onDragLeave"
      @drop="onDrop"
      @click="closeContextMenu"
      @contextmenu="openBackgroundContextMenu"
    >
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">🗂</span>
            My Drive
          </h3>
          <p class="text-sm text-gray-500 mt-1">Personal storage with a strict 1 GB limit per user.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <input
            ref="fileInputRef"
            type="file"
            class="hidden"
            @change="onFileChange"
          >

          <BaseButton variant="outline" @click="triggerFilePicker">
            Choose File
          </BaseButton>

          <BaseButton
            variant="outline"
            :disabled="isUploading"
            @click.stop="openNewFolderModal"
          >
            New Folder
          </BaseButton>

          <BaseButton
            variant="primary"
            :disabled="!canUpload"
            @click="upload"
          >
            {{ isUploading ? 'Uploading...' : 'Upload' }}
          </BaseButton>

          <BaseButton
            v-if="selectedFile"
            variant="ghost"
            :disabled="isUploading"
            @click="resetSelectedFile"
          >
            Clear
          </BaseButton>

          <div class="ml-1 flex items-center gap-1 rounded-lg border border-gray-200 bg-white p-1">
            <BaseButton
              size="sm"
              :variant="storageView === 'drive' ? 'primary' : 'ghost'"
              @click="storageView = 'drive'"
            >
              My Drive
            </BaseButton>
            <BaseButton
              size="sm"
              :variant="storageView === 'recycle' ? 'primary' : 'ghost'"
              @click="storageView = 'recycle'"
            >
              Recycle Bin ({{ recycleCount }})
            </BaseButton>
          </div>

          <div v-if="storageView === 'drive'" class="ml-1 flex items-center gap-1 rounded-lg border border-gray-200 bg-white p-1">
            <BaseButton
              size="sm"
              :variant="viewMode === 'list' ? 'primary' : 'ghost'"
              @click="viewMode = 'list'"
            >
              List
            </BaseButton>
            <BaseButton
              size="sm"
              :variant="viewMode === 'thumbnails' ? 'primary' : 'ghost'"
              @click="viewMode = 'thumbnails'"
            >
              Thumbnails
            </BaseButton>
          </div>
        </div>
      </div>

      <div class="space-y-2">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
          <span class="font-semibold text-gray-700">
            {{ formatBytes(usedBytes) }} used of {{ formatBytes(quotaBytes) }}
          </span>
          <span class="text-gray-500">
            {{ formatBytes(remainingBytes) }} remaining
          </span>
          <span class="text-gray-500">
            {{ usagePercent.toFixed(2) }}%
          </span>
        </div>

        <div class="h-3 w-full rounded-full bg-gray-200 overflow-hidden">
          <div
            class="h-full transition-all duration-300"
            :class="usageBarClass"
            :style="{ width: `${usagePercent}%` }"
          />
        </div>
      </div>

      <template v-if="storageView === 'drive'">
        <p class="text-sm text-gray-500">
          Drag a local file anywhere in this panel to select it, or drag a listed file onto a folder to move it.
        </p>

      <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex items-center gap-2">
            <BaseButton
              size="sm"
              variant="outline"
              :disabled="isRootPath || isLoading"
              @click.stop="navigateUp"
            >
              Back
            </BaseButton>
            <BaseButton
              size="sm"
              variant="ghost"
              :disabled="isRootPath || isLoading"
              @click.stop="navigateRoot"
            >
              Root
            </BaseButton>
          </div>

          <div class="text-sm flex flex-wrap items-center gap-2">
            <span class="text-gray-500">Current folder:</span>
            <span class="font-semibold text-gray-800 break-all">{{ currentPathLabel }}</span>
          </div>
        </div>
      </div>

      <div
        v-if="isSelectionMode"
        class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 flex flex-col gap-2 md:flex-row md:items-center md:justify-between"
      >
        <div class="text-sm text-emerald-900">
          <span class="font-semibold">{{ selectedCount }}</span> item(s) selected.
          Right-click a folder and choose <span class="font-semibold">Copy Selected Here</span> or <span class="font-semibold">Move Selected Here</span>.
        </div>
        <div class="flex items-center gap-2">
          <BaseButton
            size="sm"
            variant="danger"
            :disabled="selectedCount === 0 || activeFileActionId !== null"
            @click="requestDeleteSelectedItems"
          >
            Delete Selected
          </BaseButton>
          <BaseButton
            size="sm"
            variant="ghost"
            @click="clearSelectionMode"
          >
            Exit Select
          </BaseButton>
        </div>
      </div>

      <div class="rounded-xl border border-gray-200 bg-gray-50/60 overflow-hidden">
        <button
          class="w-full px-4 py-3 flex items-center justify-between text-left hover:bg-emerald-50 transition-colors"
          @click="isRecentCollapsed = !isRecentCollapsed"
        >
          <div>
            <p class="text-sm font-semibold text-gray-800">Recently Opened Files</p>
            <p class="text-xs text-gray-500">{{ recentOpenedFiles.length }} item(s)</p>
          </div>
          <span class="text-lg text-gray-500 select-none">{{ isRecentCollapsed ? '▾' : '▴' }}</span>
        </button>

        <div v-if="!isRecentCollapsed" class="border-t border-gray-200">
          <div v-if="recentOpenedFiles.length === 0" class="px-4 py-4 text-sm text-gray-500">
            No recently opened files yet.
          </div>

          <div v-else class="max-h-56 overflow-y-auto divide-y divide-gray-200">
            <button
              v-for="entry in recentOpenedFiles"
              :key="`recent-${entry.id}`"
              class="w-full px-4 py-3 flex items-center justify-between gap-3 text-left transition-colors"
              :class="entry.exists ? 'hover:bg-emerald-50' : 'bg-gray-50/70 cursor-not-allowed'"
              :disabled="!entry.exists"
              @click="openRecentFile(entry)"
            >
              <div class="flex items-center gap-3 min-w-0">
                <span
                  class="h-8 min-w-8 px-2 rounded-md border inline-flex items-center justify-center text-sm font-semibold shadow-sm"
                  :class="getFileIconBadgeClass(entry)"
                >
                  {{ getFileIconSymbol(entry) }}
                </span>
                <div class="min-w-0">
                  <p class="text-sm font-medium text-gray-800 truncate">{{ entry.original_name }}</p>
                  <p class="text-xs text-gray-500 truncate">{{ entry.mime_type || 'unknown' }}</p>
                </div>
              </div>

              <div class="shrink-0 text-right">
                <p class="text-xs text-gray-500">{{ formatRelativeOpenedTime(entry.opened_at) }}</p>
                <p v-if="!entry.exists" class="text-xs text-rose-500">File unavailable</p>
              </div>
            </button>
          </div>
        </div>
      </div>

      <div v-if="selectedFile" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
        Selected: <span class="font-semibold">{{ selectedFile.name }}</span>
        ({{ formatBytes(selectedFile.size) }})
      </div>

      <div v-if="isLoading" class="text-sm text-gray-500 py-4">
        Loading storage files...
      </div>

      <div v-else-if="driveItems.length === 0" class="text-sm text-gray-500 py-4">
        This folder is empty.
      </div>

      <div v-else class="space-y-3">
        <p class="text-xs text-gray-500">
          Click a file to open it.
        </p>

        <div v-if="viewMode === 'list'" class="overflow-x-auto border border-gray-100 rounded-lg">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 sticky top-0 z-10">
              <tr>
                <th class="text-left px-4 py-3 font-semibold">File</th>
                <th class="text-left px-4 py-3 font-semibold">Type</th>
                <th class="text-left px-4 py-3 font-semibold">Size</th>
                <th class="text-left px-4 py-3 font-semibold">Uploaded</th>
                <th class="text-right px-4 py-3 font-semibold">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="item in driveItems"
                :key="item.id"
                :class="[
                  'border-t border-gray-100 cursor-pointer transition-colors',
                  isSelectionMode && isItemSelected(item)
                    ? 'bg-emerald-100 ring-1 ring-inset ring-emerald-300'
                    : '',
                  isFolder(item) && dropTargetFolderId === item.id
                    ? 'bg-emerald-100 ring-1 ring-inset ring-emerald-300'
                    : 'hover:bg-emerald-50'
                ]"
                :draggable="!isFolder(item)"
                :title="`Click to open ${item.original_name}`"
                @click.stop="() => handleItemPrimaryAction(item)"
                @contextmenu.stop="(event) => openItemContextMenu(event, item)"
                @dragstart="(event) => onItemDragStart(event, item)"
                @dragend="onItemDragEnd"
                @dragover.stop="(event) => onFolderDragOver(event, item)"
                @dragleave.stop="(event) => onFolderDragLeave(event, item)"
                @drop.stop="(event) => onFolderDrop(event, item)"
              >
                <td class="px-4 py-3 max-w-[280px]">
                  <div class="flex items-center gap-2 min-w-0">
                    <input
                      v-if="isSelectionMode"
                      :checked="isItemSelected(item)"
                      type="checkbox"
                      class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                      @click.stop
                      @change="() => toggleSelectionForItem(item)"
                    >
                    <span
                      class="h-7 min-w-7 px-1 rounded-md border inline-flex items-center justify-center text-xs font-semibold shadow-sm"
                      :class="getFileIconBadgeClass(item)"
                      :title="`Type: ${getFileExtension(item)}`"
                    >
                      {{ getFileIconSymbol(item) }}
                    </span>
                    <p class="font-medium text-gray-800 truncate" :title="item.original_name">
                      {{ item.original_name }}
                    </p>
                  </div>
                </td>
                <td class="px-4 py-3 text-gray-600">
                  {{ isFolder(item) ? 'Folder' : (item.mime_type || 'unknown') }}
                </td>
                <td class="px-4 py-3 text-gray-600">
                  {{ isFolder(item) ? '-' : formatBytes(item.size_bytes) }}
                </td>
                <td class="px-4 py-3 text-gray-600">{{ formatDateTime(item.created_at) }}</td>
                <td class="px-4 py-3">
                  <div class="flex justify-end gap-2">
                    <BaseButton
                      v-if="!isFolder(item)"
                      size="sm"
                      variant="outline"
                      :disabled="activeFileActionId === item.id"
                      @click.stop="download(item)"
                    >
                      Download
                    </BaseButton>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          <div
            v-for="item in driveItems"
            :key="item.id"
            :class="[
              'rounded-xl border bg-white p-3 shadow-sm cursor-pointer transition',
              isSelectionMode && isItemSelected(item)
                ? 'border-emerald-500 bg-emerald-100 shadow-md'
                : '',
              isFolder(item) && dropTargetFolderId === item.id
                ? 'border-emerald-400 bg-emerald-100 shadow-md'
                : 'border-gray-200 hover:shadow-md hover:border-emerald-300 hover:bg-emerald-50'
            ]"
            :draggable="!isFolder(item)"
            :title="`Click to open ${item.original_name}`"
            @click.stop="() => handleItemPrimaryAction(item)"
            @contextmenu.stop="(event) => openItemContextMenu(event, item)"
            @dragstart="(event) => onItemDragStart(event, item)"
            @dragend="onItemDragEnd"
            @dragover.stop="(event) => onFolderDragOver(event, item)"
            @dragleave.stop="(event) => onFolderDragLeave(event, item)"
            @drop.stop="(event) => onFolderDrop(event, item)"
          >
            <div class="h-36 w-full rounded-lg bg-gray-100 overflow-hidden border border-gray-200 relative">
              <input
                v-if="isSelectionMode"
                :checked="isItemSelected(item)"
                type="checkbox"
                class="absolute top-2 right-2 z-20 h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                @click.stop
                @change="() => toggleSelectionForItem(item)"
              >
              <div
                class="absolute top-2 left-2 z-10 h-8 min-w-8 px-2 rounded-md border inline-flex items-center justify-center text-sm font-semibold shadow-sm"
                :class="getFileIconBadgeClass(item)"
                :title="`Type: ${getFileExtension(item)}`"
              >
                {{ getFileIconSymbol(item) }}
              </div>
              <img
                v-if="!isFolder(item) && isImageFile(item)"
                :src="getThumbnailSource(item)"
                :alt="item.original_name"
                class="w-full h-full object-cover"
              >
              <div v-else class="w-full h-full flex flex-col items-center justify-center text-gray-500">
                <span class="text-4xl">{{ getFileIconSymbol(item) }}</span>
                <span class="mt-2 text-xs font-semibold tracking-wide">{{ getFileExtension(item) }}</span>
                <span class="mt-1 text-[11px] text-gray-400">
                  {{ isFolder(item) ? 'Click to open folder' : 'Click to open' }}
                </span>
              </div>
            </div>

            <div class="mt-3 space-y-1">
              <p class="font-medium text-gray-800 truncate" :title="item.original_name">
                {{ item.original_name }}
              </p>
              <p class="text-xs text-gray-500 truncate" :title="item.mime_type || 'unknown'">
                {{ isFolder(item) ? 'Folder' : (item.mime_type || 'unknown') }}
              </p>
              <p class="text-xs text-gray-500">
                {{ isFolder(item) ? '-' : formatBytes(item.size_bytes) }} • {{ formatDateTime(item.created_at) }}
              </p>
            </div>

            <div class="mt-3 flex justify-end gap-2">
              <BaseButton
                v-if="!isFolder(item)"
                size="sm"
                variant="outline"
                :disabled="activeFileActionId === item.id"
                @click.stop="download(item)"
              >
                Download
              </BaseButton>
            </div>
          </div>
        </div>
      </div>
      </template>

      <template v-else>
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
          Items in recycle bin are automatically permanently deleted after {{ recycleRetentionDays }} days.
        </div>

        <div v-if="isRecycleLoading" class="text-sm text-gray-500 py-4">
          Loading recycle bin...
        </div>

        <div v-else-if="recycleItems.length === 0" class="text-sm text-gray-500 py-4">
          Recycle bin is empty.
        </div>

        <div v-else class="overflow-x-auto border border-gray-100 rounded-lg">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 sticky top-0 z-10">
              <tr>
                <th class="text-left px-4 py-3 font-semibold">Item</th>
                <th class="text-left px-4 py-3 font-semibold">Location</th>
                <th class="text-left px-4 py-3 font-semibold">Deleted</th>
                <th class="text-left px-4 py-3 font-semibold">Expires In</th>
                <th class="text-right px-4 py-3 font-semibold">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="item in recycleItems"
                :key="`recycle-${item.id}`"
                class="border-t border-gray-100 hover:bg-amber-50/40 transition-colors"
              >
                <td class="px-4 py-3 max-w-[280px]">
                  <div class="flex items-center gap-2 min-w-0">
                    <span
                      class="h-7 min-w-7 px-1 rounded-md border inline-flex items-center justify-center text-xs font-semibold shadow-sm"
                      :class="getFileIconBadgeClass(item)"
                    >
                      {{ item.item_type === 'folder' ? '📁' : getFileIconSymbol(item) }}
                    </span>
                    <div class="min-w-0">
                      <p class="font-medium text-gray-800 truncate" :title="item.original_name">
                        {{ item.original_name }}
                      </p>
                      <p class="text-xs text-gray-500">{{ item.item_type === 'folder' ? 'Folder' : (item.mime_type || 'unknown') }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-4 py-3 text-gray-600 break-all">{{ formatRecycleLocation(item) }}</td>
                <td class="px-4 py-3 text-gray-600">{{ formatDateTime(item.deleted_at) }}</td>
                <td class="px-4 py-3 text-gray-600">
                  <span :class="item.days_remaining <= 3 ? 'text-red-600 font-semibold' : ''">
                    {{ item.days_remaining }} day(s)
                  </span>
                </td>
                <td class="px-4 py-3">
                  <div class="flex justify-end gap-2">
                    <BaseButton
                      size="sm"
                      variant="outline"
                      :disabled="activeFileActionId === item.id"
                      @click="restoreRecycleItem(item)"
                    >
                      Restore
                    </BaseButton>
                    <BaseButton
                      size="sm"
                      variant="danger"
                      :disabled="activeFileActionId === item.id"
                      @click="permanentlyDeleteRecycleItem(item)"
                    >
                      Delete Now
                    </BaseButton>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>
  </BaseCard>

  <div
    v-if="contextMenu.visible"
    class="fixed z-[80] min-w-[200px] rounded-lg border border-gray-200 bg-white shadow-lg py-1"
    :style="{ left: `${contextMenu.x}px`, top: `${contextMenu.y}px` }"
    @click.stop
    @contextmenu.prevent
  >
    <button
      v-if="contextMenu.item"
      class="w-full px-3 py-2 text-left text-sm hover:bg-emerald-50"
      @click="selectFromContextMenu"
    >
      Select
    </button>
    <button
      v-if="contextMenu.item"
      class="w-full px-3 py-2 text-left text-sm hover:bg-emerald-50"
      @click="openFromContextMenu"
    >
      {{ contextMenu.item.item_type === 'folder' ? 'Open Folder' : 'Open' }}
    </button>
    <button
      v-if="contextMenu.item && contextMenu.item.item_type === 'file'"
      class="w-full px-3 py-2 text-left text-sm hover:bg-emerald-50"
      @click="downloadFromContextMenu"
    >
      Download
    </button>
    <button
      v-if="isSelectionMode && selectedCount > 0 && contextMenu.item && contextMenu.item.item_type === 'folder'"
      class="w-full px-3 py-2 text-left text-sm hover:bg-emerald-50"
      @click="copySelectedToContextFolder"
    >
      Copy Selected Here
    </button>
    <button
      v-if="isSelectionMode && selectedCount > 0 && contextMenu.item && contextMenu.item.item_type === 'folder'"
      class="w-full px-3 py-2 text-left text-sm hover:bg-emerald-50"
      @click="moveSelectedToContextFolder"
    >
      Move Selected Here
    </button>
    <button
      v-if="isSelectionMode && selectedCount > 0"
      class="w-full px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50"
      @click="deleteSelectedFromContextMenu"
    >
      Delete Selected
    </button>
    <button
      v-if="contextMenu.item"
      class="w-full px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50"
      @click="deleteFromContextMenu"
    >
      Delete
    </button>
    <button
      class="w-full px-3 py-2 text-left text-sm hover:bg-emerald-50"
      @click="openNewFolderModal"
    >
      New Folder
    </button>
    <button
      v-if="isSelectionMode"
      class="w-full px-3 py-2 text-left text-sm hover:bg-emerald-50"
      @click="clearSelectionMode"
    >
      Exit Select
    </button>
  </div>

  <div
    v-if="isNewFolderModalOpen"
    class="fixed inset-0 z-[85] bg-black/45 flex items-center justify-center p-4"
    @click.self="closeNewFolderModal"
  >
    <div class="w-full max-w-md rounded-xl border border-gray-200 bg-white shadow-xl overflow-hidden">
      <div class="px-4 py-3 border-b border-gray-200">
        <p class="text-base font-semibold text-gray-800">Create New Folder</p>
        <p class="text-xs text-gray-500 mt-1">Folder will be created in: {{ currentPathLabel }}</p>
      </div>

      <div class="px-4 py-4 space-y-2">
        <label for="new-folder-name" class="text-sm font-medium text-gray-700">Folder name</label>
        <input
          id="new-folder-name"
          ref="newFolderInputRef"
          v-model="newFolderName"
          type="text"
          maxlength="120"
          class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200"
          placeholder="e.g. Reports 2026"
          @keydown.enter.prevent="submitNewFolder"
        >
      </div>

      <div class="px-4 py-3 border-t border-gray-200 bg-gray-50 flex justify-end gap-2">
        <BaseButton
          variant="ghost"
          @click="closeNewFolderModal"
        >
          Cancel
        </BaseButton>
        <BaseButton
          variant="primary"
          :disabled="!newFolderName.trim() || isUploading || activeFileActionId !== null"
          @click="submitNewFolder"
        >
          Create Folder
        </BaseButton>
      </div>
    </div>
  </div>

  <div
    v-if="isDeleteConfirmModalOpen"
    class="fixed inset-0 z-[88] bg-black/45 flex items-center justify-center p-4"
    @click.self="closeDeleteConfirmDialog"
  >
    <div class="w-full max-w-md rounded-xl border border-gray-200 bg-white shadow-xl overflow-hidden">
      <div class="px-4 py-3 border-b border-gray-200">
        <p class="text-base font-semibold text-gray-800">{{ deleteConfirmTitle }}</p>
      </div>

      <div class="px-4 py-4">
        <p class="text-sm text-gray-700">{{ deleteConfirmMessage }}</p>
      </div>

      <div class="px-4 py-3 border-t border-gray-200 bg-gray-50 flex justify-end gap-2">
        <BaseButton
          variant="ghost"
          :disabled="isDeleteConfirmSubmitting"
          @click="closeDeleteConfirmDialog"
        >
          Cancel
        </BaseButton>
        <BaseButton
          variant="danger"
          :disabled="isDeleteConfirmSubmitting"
          @click="confirmDeleteDialog"
        >
          {{ isDeleteConfirmSubmitting ? 'Processing...' : 'Confirm' }}
        </BaseButton>
      </div>
    </div>
  </div>

  <div
    v-if="previewFile"
    class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4"
    @click.self="closePreview"
  >
    <div class="w-full max-w-6xl h-[85vh] bg-white rounded-xl shadow-xl overflow-hidden flex flex-col">
      <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
        <p class="font-semibold text-gray-800 truncate" :title="previewFile.original_name">
          {{ previewFile.original_name }}
        </p>
        <div class="flex items-center gap-2">
          <BaseButton
            size="sm"
            variant="outline"
            @click="downloadPreviewFile"
          >
            Download
          </BaseButton>
          <BaseButton
            size="sm"
            variant="ghost"
            @click="closePreview"
          >
            Close
          </BaseButton>
        </div>
      </div>

      <div class="flex-1 bg-gray-100">
        <img
          v-if="isImagePreview"
          :src="previewSrc"
          :alt="previewFile.original_name"
          class="w-full h-full object-contain"
        >
        <iframe
          v-else
          :src="previewSrc"
          class="w-full h-full bg-white"
          title="File preview"
        />
      </div>
    </div>
  </div>
</template>
