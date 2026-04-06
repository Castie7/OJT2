<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import BaseButton from '../../ui/BaseButton.vue'
import { useWorkspaceStorage } from '../../../composables/useWorkspaceStorage'
import { storageService } from '../../../services'
import type { WorkspaceStorageFile, WorkspaceStorageFolder, WorkspaceStorageRecycleItem } from '../../../types'

const fileInputRef = ref<HTMLInputElement | null>(null)
const isDragOver = ref(false)
const dragDepth = ref(0)
const draggedItemId = ref<number | null>(null)
const dropTargetFolderId = ref<number | null>(null)

const {
  files, folders, recycleItems, recycleRetentionDays,
  currentPath, selectedFile, isLoading, isRecycleLoading,
  isUploading, activeFileActionId, quotaBytes, usedBytes,
  remainingBytes, usagePercent, canUpload, fetchSummary,
  fetchRecycleBin, navigateToFolder,
  handleFileSelection, setSelectedFile, clearSelectedFile,
  uploadSelectedFile, createFolder, downloadFile, deleteFile,
  moveItem, copyItem, restoreFromRecycleBin,
  permanentlyDeleteFromRecycleBin, formatBytes, formatDateTime
} = useWorkspaceStorage()

type DriveItem = WorkspaceStorageFile | WorkspaceStorageFolder

const usageBarClass = computed(() => {
  if (usagePercent.value >= 90) return 'bg-red-500'
  if (usagePercent.value >= 70) return 'bg-amber-500'
  return 'bg-emerald-500'
})

const previewFile = ref<WorkspaceStorageFile | null>(null)
const previewSrc = ref('')
const storageView = ref<'drive' | 'recycle'>('drive')

const contextMenu = ref<{ visible: boolean; x: number; y: number; item: DriveItem | null }>({
  visible: false, x: 0, y: 0, item: null
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
  return [...folderItems, ...files.value]
})
const recycleCount = computed(() => recycleItems.value.length)
const selectedDriveItems = computed<DriveItem[]>(() => {
  if (selectedItemIds.value.length === 0) return []
  const selectedSet = new Set(selectedItemIds.value)
  return driveItems.value.filter((item) => selectedSet.has(item.id))
})
const selectedCount = computed(() => selectedDriveItems.value.length)
const isDraggingDriveItem = computed(() => draggedItemId.value !== null)

const findDriveItemById = (id: number): DriveItem | null => {
  for (const folder of folders.value) { if (folder.id === id) return folder }
  for (const file of files.value) { if (file.id === id) return file }
  return null
}

const isInlinePreviewable = (file: WorkspaceStorageFile): boolean => {
  const mime = (file.mime_type || '').toLowerCase()
  const name = (file.original_name || '').toLowerCase()
  return mime.startsWith('image/') || mime === 'application/pdf' || name.endsWith('.pdf')
}

const isFolder = (item: DriveItem): item is WorkspaceStorageFolder => item.item_type === 'folder'

const isImagePreview = computed(() => {
  const file = previewFile.value
  if (!file) return false
  const mime = (file.mime_type || '').toLowerCase()
  const name = (file.original_name || '').toLowerCase()
  return mime.startsWith('image/') || /\.(png|jpe?g|gif|webp|bmp|svg)$/i.test(name)
})

const isImageFile = (file: { original_name: string; mime_type: string }): boolean => {
  const mime = (file.mime_type || '').toLowerCase()
  const name = (file.original_name || '').toLowerCase()
  return mime.startsWith('image/') || /\.(png|jpe?g|gif|webp|bmp|svg)$/i.test(name)
}

const getFileIcon = (file: { original_name: string; mime_type: string }): string => {
  const mime = (file.mime_type || '').toLowerCase()
  const name = (file.original_name || '').toLowerCase()
  if (isImageFile(file)) return '🖼️'
  if (mime === 'application/pdf' || name.endsWith('.pdf')) return '📕'
  if (mime.includes('word') || name.endsWith('.doc') || name.endsWith('.docx')) return '📝'
  if (mime.includes('excel') || mime.includes('spreadsheet') || name.endsWith('.xls') || name.endsWith('.xlsx') || name.endsWith('.csv')) return '📊'
  if (mime.includes('zip') || mime.includes('rar') || name.endsWith('.zip') || name.endsWith('.rar')) return '🗜️'
  if (mime.startsWith('text/') || name.endsWith('.txt')) return '📄'
  return '📄'
}

const getFileExt = (file: { original_name: string }): string => {
  const name = (file.original_name || '').trim()
  const i = name.lastIndexOf('.')
  if (i === -1 || i === name.length - 1) return ''
  return name.slice(i + 1).toUpperCase()
}

const getThumbnailSource = (file: WorkspaceStorageFile): string => storageService.getOpenUrl(file.id)

// Breadcrumb segments
const breadcrumbs = computed(() => {
  if (currentPath.value === '/') return [{ label: 'My Drive', path: '/' }]
  const parts = currentPath.value.split('/').filter(Boolean)
  const crumbs = [{ label: 'My Drive', path: '/' }]
  let accumulated = ''
  for (const part of parts) {
    accumulated += '/' + part
    crumbs.push({ label: part, path: accumulated })
  }
  return crumbs
})

const triggerFilePicker = () => { if (!isUploading.value) fileInputRef.value?.click() }
const onFileChange = (event: Event) => { handleFileSelection(event) }
const resetSelectedFile = () => { clearSelectedFile(); if (fileInputRef.value) fileInputRef.value.value = '' }

const upload = async () => {
  await uploadSelectedFile()
  if (!selectedFile.value && fileInputRef.value) fileInputRef.value.value = ''
}

const download = async (file: WorkspaceStorageFile) => { await downloadFile(file) }

const open = async (file: WorkspaceStorageFile) => {
  if (isInlinePreviewable(file)) {
    previewFile.value = file
    previewSrc.value = storageService.getOpenUrl(file.id)
    return
  }
  await downloadFile(file)
}

const openItem = async (item: DriveItem) => {
  closeContextMenu()
  if (isFolder(item)) { await navigateToFolder(item.full_path); return }
  await open(item)
}

const isItemSelected = (item: DriveItem): boolean => selectedItemIds.value.includes(item.id)
const clearSelectionMode = () => { isSelectionMode.value = false; selectedItemIds.value = [] }
const enableSelectionMode = (item?: DriveItem) => {
  isSelectionMode.value = true
  if (item && !selectedItemIds.value.includes(item.id)) {
    selectedItemIds.value = [...selectedItemIds.value, item.id]
  }
}
const toggleSelectionForItem = (item: DriveItem) => {
  if (!isSelectionMode.value) return
  const s = new Set(selectedItemIds.value)
  s.has(item.id) ? s.delete(item.id) : s.add(item.id)
  selectedItemIds.value = Array.from(s)
}
const handleItemPrimaryAction = async (item: DriveItem) => {
  if (isSelectionMode.value) { toggleSelectionForItem(item); return }
  await openItem(item)
}

const openDeleteConfirmDialog = (title: string, message: string, action: () => Promise<void>) => {
  deleteConfirmTitle.value = title
  deleteConfirmMessage.value = message
  pendingDeleteAction.value = action
  isDeleteConfirmSubmitting.value = false
  isDeleteConfirmModalOpen.value = true
}
const closeDeleteConfirmDialog = () => {
  isDeleteConfirmModalOpen.value = false
  pendingDeleteAction.value = null
  isDeleteConfirmSubmitting.value = false
}
const confirmDeleteDialog = async () => {
  if (!pendingDeleteAction.value || isDeleteConfirmSubmitting.value) return
  isDeleteConfirmSubmitting.value = true
  try { await pendingDeleteAction.value() } finally { closeDeleteConfirmDialog() }
}

const requestDeleteItem = (item: DriveItem) => {
  openDeleteConfirmDialog('Move To Recycle Bin', `Move "${item.original_name}" to Recycle Bin?`, async () => {
    const deleted = await deleteFile(item)
    if (deleted) await fetchRecycleBin()
  })
}
const requestDeleteSelectedItems = () => {
  if (selectedCount.value === 0) return
  openDeleteConfirmDialog('Move Selected Items', `Move ${selectedCount.value} item(s) to Recycle Bin?`, async () => {
    const items = [...selectedDriveItems.value]
    let any = false
    for (const item of items) { if (await deleteFile(item)) any = true }
    if (any) await fetchRecycleBin()
    clearSelectionMode()
  })
}

const openNewFolderModal = async () => {
  closeContextMenu()
  isNewFolderModalOpen.value = true
  newFolderName.value = ''
  await nextTick()
  newFolderInputRef.value?.focus()
}
const closeNewFolderModal = () => { isNewFolderModalOpen.value = false; newFolderName.value = '' }
const submitNewFolder = async () => { if (await createFolder(newFolderName.value)) closeNewFolderModal() }

const closeContextMenu = () => { contextMenu.value.visible = false; contextMenu.value.item = null }
const openItemContextMenu = (event: MouseEvent, item: DriveItem) => {
  if (storageView.value !== 'drive') return
  event.preventDefault()
  contextMenu.value = { visible: true, x: event.clientX, y: event.clientY, item }
}
const openBackgroundContextMenu = (event: MouseEvent) => {
  if (storageView.value !== 'drive') return
  event.preventDefault()
  contextMenu.value = { visible: true, x: event.clientX, y: event.clientY, item: null }
}
const openFromContextMenu = async () => { if (contextMenu.value.item) await openItem(contextMenu.value.item) }
const downloadFromContextMenu = async () => {
  if (!contextMenu.value.item || isFolder(contextMenu.value.item)) return
  const s = contextMenu.value.item; closeContextMenu(); await download(s)
}
const selectFromContextMenu = () => { if (contextMenu.value.item) { enableSelectionMode(contextMenu.value.item); closeContextMenu() } }
const deleteFromContextMenu = () => { if (contextMenu.value.item) { const i = contextMenu.value.item; closeContextMenu(); requestDeleteItem(i) } }
const moveSelectedToContextFolder = async () => {
  const t = contextMenu.value.item
  if (!t || !isFolder(t) || selectedCount.value === 0) return
  closeContextMenu()
  for (const item of [...selectedDriveItems.value]) await moveItem(item, t.full_path)
  clearSelectionMode()
}
const copySelectedToContextFolder = async () => {
  const t = contextMenu.value.item
  if (!t || !isFolder(t) || selectedCount.value === 0) return
  closeContextMenu()
  for (const item of [...selectedDriveItems.value]) await copyItem(item, t.full_path)
}
const deleteSelectedFromContextMenu = () => { closeContextMenu(); requestDeleteSelectedItems() }

const restoreRecycleItem = async (item: WorkspaceStorageRecycleItem) => {
  if (await restoreFromRecycleBin(item)) await fetchRecycleBin()
}
const permanentlyDeleteRecycleItem = async (item: WorkspaceStorageRecycleItem) => {
  openDeleteConfirmDialog('Permanent Delete', `Permanently delete "${item.original_name}"? This cannot be undone.`, async () => {
    if (await permanentlyDeleteFromRecycleBin(item)) await fetchRecycleBin()
  })
}

const closePreview = () => { previewFile.value = null; previewSrc.value = '' }
const downloadPreviewFile = async () => { if (previewFile.value) await download(previewFile.value) }

const hasExternalFiles = (event: DragEvent): boolean => {
  const t = event.dataTransfer?.types
  return t ? Array.from(t).includes('Files') : false
}
const onItemDragStart = (event: DragEvent, item: DriveItem) => {
  if (isFolder(item) || isUploading.value || activeFileActionId.value !== null) { event.preventDefault(); return }
  draggedItemId.value = item.id; dropTargetFolderId.value = null
  if (event.dataTransfer) { event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', String(item.id)) }
}
const onItemDragEnd = () => { draggedItemId.value = null; dropTargetFolderId.value = null }
const onFolderDragOver = (event: DragEvent, item: DriveItem) => {
  if (!isFolder(item) || draggedItemId.value === null) return
  const d = findDriveItemById(draggedItemId.value)
  if (!d || isFolder(d) || d.folder_path === item.full_path) return
  event.preventDefault(); event.stopPropagation(); dropTargetFolderId.value = item.id
  if (event.dataTransfer) event.dataTransfer.dropEffect = 'move'
}
const onFolderDragLeave = (_e: DragEvent, item: DriveItem) => {
  if (isFolder(item) && dropTargetFolderId.value === item.id) dropTargetFolderId.value = null
}
const onFolderDrop = async (event: DragEvent, item: DriveItem) => {
  if (!isFolder(item)) return
  event.preventDefault(); event.stopPropagation()
  const did = draggedItemId.value; draggedItemId.value = null; dropTargetFolderId.value = null
  if (did === null || isUploading.value || activeFileActionId.value !== null) return
  const d = findDriveItemById(did)
  if (!d || isFolder(d) || d.folder_path === item.full_path) return
  await moveItem(d, item.full_path)
}
const onDragEnter = (e: DragEvent) => { e.preventDefault(); if (!isDraggingDriveItem.value && !isUploading.value && hasExternalFiles(e)) { dragDepth.value += 1; isDragOver.value = true } }
const onDragOver = (e: DragEvent) => { e.preventDefault(); if (!isDraggingDriveItem.value && !isUploading.value && hasExternalFiles(e)) isDragOver.value = true }
const onDragLeave = (e: DragEvent) => { e.preventDefault(); if (!isDraggingDriveItem.value && !isUploading.value && hasExternalFiles(e)) { dragDepth.value = Math.max(0, dragDepth.value - 1); if (dragDepth.value === 0) isDragOver.value = false } }
const onDrop = (e: DragEvent) => { e.preventDefault(); dragDepth.value = 0; isDragOver.value = false; if (!isDraggingDriveItem.value && !isUploading.value && hasExternalFiles(e)) setSelectedFile(e.dataTransfer?.files?.[0] ?? null) }

watch(currentPath, () => clearSelectionMode())
watch(storageView, (v) => { closeContextMenu(); if (v !== 'drive') clearSelectionMode() })

const handleGlobalClick = () => closeContextMenu()
const handleEscapeKey = (e: KeyboardEvent) => { if (e.key === 'Escape') { closeDeleteConfirmDialog(); closeNewFolderModal(); closeContextMenu(); closePreview() } }

onMounted(() => { fetchSummary(); fetchRecycleBin(); window.addEventListener('click', handleGlobalClick); window.addEventListener('keydown', handleEscapeKey) })
onBeforeUnmount(() => { window.removeEventListener('click', handleGlobalClick); window.removeEventListener('keydown', handleEscapeKey) })
</script>

<template>
  <div
    class="drive-panel"
    :class="isDragOver ? 'drive-panel--dragover' : ''"
    @dragenter="onDragEnter" @dragover="onDragOver" @dragleave="onDragLeave" @drop="onDrop"
    @click="closeContextMenu" @contextmenu="openBackgroundContextMenu"
  >
    <!-- ═══ STORAGE BAR ═══ -->
    <div class="storage-bar">
      <div class="storage-bar__info">
        <span class="storage-bar__used">{{ formatBytes(usedBytes) }}</span>
        <span class="storage-bar__sep">of {{ formatBytes(quotaBytes) }}</span>
        <span class="storage-bar__remaining">· {{ formatBytes(remainingBytes) }} free</span>
      </div>
      <div class="storage-bar__track">
        <div class="storage-bar__fill" :class="usageBarClass" :style="{ width: `${usagePercent}%` }" />
      </div>
    </div>

    <!-- ═══ TOOLBAR ═══ -->
    <div class="toolbar">
      <div class="toolbar__tabs">
        <button
          :class="['toolbar__tab', storageView === 'drive' ? 'toolbar__tab--active' : '']"
          @click="storageView = 'drive'"
        >
          📁 Files
        </button>
        <button
          :class="['toolbar__tab', storageView === 'recycle' ? 'toolbar__tab--active' : '']"
          @click="storageView = 'recycle'"
        >
          🗑️ Trash
          <span v-if="recycleCount > 0" class="toolbar__badge">{{ recycleCount }}</span>
        </button>
      </div>

      <div v-if="storageView === 'drive'" class="toolbar__actions">
        <input ref="fileInputRef" type="file" class="hidden" @change="onFileChange">
        <button class="action-btn" title="Upload file" @click="triggerFilePicker" :disabled="isUploading">
          <span>⬆️</span> Upload
        </button>
        <button class="action-btn" title="New folder" @click.stop="openNewFolderModal" :disabled="isUploading">
          <span>➕</span> New Folder
        </button>
        <button
          v-if="!isSelectionMode"
          class="action-btn action-btn--ghost"
          title="Select multiple items"
          @click="enableSelectionMode()"
        >
          ☑️ Select
        </button>
        <button
          v-if="isSelectionMode"
          class="action-btn action-btn--ghost"
          @click="clearSelectionMode"
        >
          ✕ Cancel
        </button>
      </div>
    </div>

    <!-- ═══ SELECTED FILE BANNER ═══ -->
    <div v-if="selectedFile" class="upload-banner">
      <div class="upload-banner__info">
        <span class="upload-banner__icon">📎</span>
        <span class="upload-banner__name">{{ selectedFile.name }}</span>
        <span class="upload-banner__size">({{ formatBytes(selectedFile.size) }})</span>
      </div>
      <div class="upload-banner__actions">
        <BaseButton variant="primary" size="sm" :disabled="!canUpload" @click="upload">
          {{ isUploading ? 'Uploading…' : 'Upload Now' }}
        </BaseButton>
        <button class="upload-banner__clear" :disabled="isUploading" @click="resetSelectedFile">✕</button>
      </div>
    </div>

    <!-- ═══ SELECTION BAR ═══ -->
    <div v-if="isSelectionMode && selectedCount > 0" class="selection-bar">
      <span>{{ selectedCount }} selected</span>
      <div class="selection-bar__actions">
        <BaseButton size="sm" variant="danger" :disabled="activeFileActionId !== null" @click="requestDeleteSelectedItems">
          Delete
        </BaseButton>
      </div>
    </div>

    <!-- ═══ DRIVE VIEW ═══ -->
    <template v-if="storageView === 'drive'">
      <!-- Breadcrumb -->
      <nav class="breadcrumb">
        <template v-for="(crumb, idx) in breadcrumbs" :key="crumb.path">
          <span v-if="idx > 0" class="breadcrumb__sep">›</span>
          <button
            :class="['breadcrumb__item', idx === breadcrumbs.length - 1 ? 'breadcrumb__item--current' : '']"
            :disabled="idx === breadcrumbs.length - 1"
            @click="navigateToFolder(crumb.path)"
          >
            {{ crumb.label }}
          </button>
        </template>
      </nav>

      <!-- Loading -->
      <div v-if="isLoading" class="empty-state">
        <span class="empty-state__icon">⏳</span>
        <p>Loading files…</p>
      </div>

      <!-- Empty -->
      <div v-else-if="driveItems.length === 0" class="empty-state">
        <span class="empty-state__icon">📂</span>
        <p>This folder is empty</p>
        <p class="empty-state__hint">Upload a file or create a folder to get started</p>
      </div>

      <!-- File Grid -->
      <div v-else class="file-grid">
        <div
          v-for="item in driveItems"
          :key="item.id"
          :class="[
            'file-card',
            isSelectionMode && isItemSelected(item) ? 'file-card--selected' : '',
            isFolder(item) && dropTargetFolderId === item.id ? 'file-card--drop-target' : ''
          ]"
          :draggable="!isFolder(item)"
          @click.stop="() => handleItemPrimaryAction(item)"
          @contextmenu.stop="(e) => openItemContextMenu(e, item)"
          @dragstart="(e) => onItemDragStart(e, item)" @dragend="onItemDragEnd"
          @dragover.stop="(e) => onFolderDragOver(e, item)"
          @dragleave.stop="(e) => onFolderDragLeave(e, item)"
          @drop.stop="(e) => onFolderDrop(e, item)"
        >
          <input
            v-if="isSelectionMode"
            :checked="isItemSelected(item)"
            type="checkbox"
            class="file-card__checkbox"
            @click.stop @change="() => toggleSelectionForItem(item)"
          >

          <div class="file-card__preview">
            <img
              v-if="!isFolder(item) && isImageFile(item)"
              :src="getThumbnailSource(item)"
              :alt="item.original_name"
              class="file-card__thumb"
            >
            <div v-else class="file-card__icon-wrap">
              <span class="file-card__icon">{{ isFolder(item) ? '📁' : getFileIcon(item) }}</span>
              <span v-if="!isFolder(item)" class="file-card__ext">{{ getFileExt(item) }}</span>
            </div>
          </div>

          <div class="file-card__meta">
            <p class="file-card__name" :title="item.original_name">{{ item.original_name }}</p>
            <p class="file-card__detail">
              {{ isFolder(item) ? 'Folder' : formatBytes(item.size_bytes) }}
            </p>
          </div>
        </div>
      </div>
    </template>

    <!-- ═══ RECYCLE BIN VIEW ═══ -->
    <template v-else>
      <div class="recycle-notice">
        Items are permanently deleted after {{ recycleRetentionDays }} days.
      </div>

      <div v-if="isRecycleLoading" class="empty-state">
        <span class="empty-state__icon">⏳</span>
        <p>Loading…</p>
      </div>

      <div v-else-if="recycleItems.length === 0" class="empty-state">
        <span class="empty-state__icon">🗑️</span>
        <p>Trash is empty</p>
      </div>

      <div v-else class="recycle-list">
        <div
          v-for="item in recycleItems"
          :key="`recycle-${item.id}`"
          class="recycle-item"
        >
          <div class="recycle-item__info">
            <span class="recycle-item__icon">{{ item.item_type === 'folder' ? '📁' : getFileIcon(item) }}</span>
            <div class="recycle-item__text">
              <p class="recycle-item__name">{{ item.original_name }}</p>
              <p class="recycle-item__detail">
                {{ formatDateTime(item.deleted_at) }}
                · <span :class="item.days_remaining <= 3 ? 'text-red-600 font-semibold' : ''">{{ item.days_remaining }}d left</span>
              </p>
            </div>
          </div>
          <div class="recycle-item__actions">
            <button class="action-btn action-btn--sm" :disabled="activeFileActionId === item.id" @click="restoreRecycleItem(item)">Restore</button>
            <button class="action-btn action-btn--sm action-btn--danger" :disabled="activeFileActionId === item.id" @click="permanentlyDeleteRecycleItem(item)">Delete</button>
          </div>
        </div>
      </div>
    </template>

    <!-- Drag overlay -->
    <div v-if="isDragOver" class="drag-overlay">
      <div class="drag-overlay__content">
        <span class="drag-overlay__icon">⬆️</span>
        <p>Drop file to upload</p>
      </div>
    </div>
  </div>

  <!-- ═══ CONTEXT MENU ═══ -->
  <div
    v-if="contextMenu.visible"
    class="ctx-menu"
    :style="{ left: `${contextMenu.x}px`, top: `${contextMenu.y}px` }"
    @click.stop @contextmenu.prevent
  >
    <button v-if="contextMenu.item" class="ctx-menu__item" @click="openFromContextMenu">
      {{ contextMenu.item.item_type === 'folder' ? '📂 Open Folder' : '📄 Open' }}
    </button>
    <button v-if="contextMenu.item && contextMenu.item.item_type === 'file'" class="ctx-menu__item" @click="downloadFromContextMenu">⬇️ Download</button>
    <button v-if="contextMenu.item" class="ctx-menu__item" @click="selectFromContextMenu">☑️ Select</button>
    <div v-if="isSelectionMode && selectedCount > 0 && contextMenu.item && contextMenu.item.item_type === 'folder'" class="ctx-menu__divider" />
    <button v-if="isSelectionMode && selectedCount > 0 && contextMenu.item && contextMenu.item.item_type === 'folder'" class="ctx-menu__item" @click="copySelectedToContextFolder">📋 Copy Selected Here</button>
    <button v-if="isSelectionMode && selectedCount > 0 && contextMenu.item && contextMenu.item.item_type === 'folder'" class="ctx-menu__item" @click="moveSelectedToContextFolder">📦 Move Selected Here</button>
    <div class="ctx-menu__divider" />
    <button class="ctx-menu__item" @click="openNewFolderModal">➕ New Folder</button>
    <button v-if="contextMenu.item" class="ctx-menu__item ctx-menu__item--danger" @click="deleteFromContextMenu">🗑️ Delete</button>
    <button v-if="isSelectionMode && selectedCount > 0" class="ctx-menu__item ctx-menu__item--danger" @click="deleteSelectedFromContextMenu">🗑️ Delete Selected</button>
  </div>

  <!-- ═══ NEW FOLDER MODAL ═══ -->
  <div v-if="isNewFolderModalOpen" class="modal-overlay" @click.self="closeNewFolderModal">
    <div class="modal">
      <h3 class="modal__title">New Folder</h3>
      <input
        ref="newFolderInputRef" v-model="newFolderName"
        type="text" maxlength="120" class="modal__input"
        placeholder="Folder name…"
        @keydown.enter.prevent="submitNewFolder"
      >
      <div class="modal__footer">
        <BaseButton variant="ghost" @click="closeNewFolderModal">Cancel</BaseButton>
        <BaseButton variant="primary" :disabled="!newFolderName.trim() || isUploading || activeFileActionId !== null" @click="submitNewFolder">Create</BaseButton>
      </div>
    </div>
  </div>

  <!-- ═══ DELETE CONFIRM MODAL ═══ -->
  <div v-if="isDeleteConfirmModalOpen" class="modal-overlay" @click.self="closeDeleteConfirmDialog">
    <div class="modal">
      <h3 class="modal__title">{{ deleteConfirmTitle }}</h3>
      <p class="modal__message">{{ deleteConfirmMessage }}</p>
      <div class="modal__footer">
        <BaseButton variant="ghost" :disabled="isDeleteConfirmSubmitting" @click="closeDeleteConfirmDialog">Cancel</BaseButton>
        <BaseButton variant="danger" :disabled="isDeleteConfirmSubmitting" @click="confirmDeleteDialog">
          {{ isDeleteConfirmSubmitting ? 'Processing…' : 'Confirm' }}
        </BaseButton>
      </div>
    </div>
  </div>

  <!-- ═══ FILE PREVIEW MODAL ═══ -->
  <div v-if="previewFile" class="modal-overlay modal-overlay--dark" @click.self="closePreview">
    <div class="preview-modal">
      <div class="preview-modal__header">
        <p class="preview-modal__name" :title="previewFile.original_name">{{ previewFile.original_name }}</p>
        <div class="preview-modal__actions">
          <BaseButton size="sm" variant="outline" @click="downloadPreviewFile">Download</BaseButton>
          <BaseButton size="sm" variant="ghost" @click="closePreview">Close</BaseButton>
        </div>
      </div>
      <div class="preview-modal__body">
        <img v-if="isImagePreview" :src="previewSrc" :alt="previewFile.original_name" class="preview-modal__img">
        <iframe v-else :src="previewSrc" class="preview-modal__iframe" title="File preview" />
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ═══════════════════════════════════════════
   DESIGN TOKENS
   ═══════════════════════════════════════════ */
:root {
  --drive-radius: 12px;
  --drive-accent: #059669;
  --drive-accent-light: #d1fae5;
  --drive-border: #e5e7eb;
  --drive-bg: #f9fafb;
}

/* ═══ PANEL ═══ */
.drive-panel {
  position: relative;
  background: white;
  border: 1px solid var(--drive-border);
  border-radius: var(--drive-radius);
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  transition: box-shadow 0.2s;
}
.drive-panel--dragover {
  box-shadow: inset 0 0 0 2px var(--drive-accent);
  background: #ecfdf5;
}

/* ═══ STORAGE BAR ═══ */
.storage-bar {
  display: flex;
  align-items: center;
  gap: 12px;
}
.storage-bar__info {
  font-size: 13px;
  white-space: nowrap;
  flex-shrink: 0;
}
.storage-bar__used { font-weight: 600; color: #1f2937; }
.storage-bar__sep { color: #6b7280; }
.storage-bar__remaining { color: #9ca3af; font-size: 12px; }
.storage-bar__track {
  flex: 1;
  height: 6px;
  background: #e5e7eb;
  border-radius: 99px;
  overflow: hidden;
}
.storage-bar__fill {
  height: 100%;
  border-radius: 99px;
  transition: width 0.4s ease;
}

/* ═══ TOOLBAR ═══ */
.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}
.toolbar__tabs {
  display: flex;
  gap: 2px;
  background: #f3f4f6;
  border-radius: 8px;
  padding: 3px;
}
.toolbar__tab {
  padding: 6px 14px;
  font-size: 13px;
  font-weight: 500;
  border: none;
  border-radius: 6px;
  background: transparent;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.15s;
  display: flex;
  align-items: center;
  gap: 6px;
}
.toolbar__tab:hover { color: #374151; background: #e5e7eb; }
.toolbar__tab--active {
  background: white;
  color: #059669;
  font-weight: 600;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}
.toolbar__badge {
  background: #ef4444;
  color: white;
  font-size: 10px;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: 99px;
  line-height: 1.4;
}
.toolbar__actions {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

/* ═══ ACTION BUTTONS ═══ */
.action-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  font-size: 13px;
  font-weight: 500;
  border: 1px solid var(--drive-border);
  border-radius: 8px;
  background: white;
  color: #374151;
  cursor: pointer;
  transition: all 0.15s;
}
.action-btn:hover:not(:disabled) { background: #f9fafb; border-color: #d1d5db; }
.action-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.action-btn--ghost { border-color: transparent; background: transparent; }
.action-btn--ghost:hover:not(:disabled) { background: #f3f4f6; }
.action-btn--sm { padding: 4px 10px; font-size: 12px; }
.action-btn--danger { color: #dc2626; border-color: #fecaca; }
.action-btn--danger:hover:not(:disabled) { background: #fef2f2; }

/* ═══ UPLOAD BANNER ═══ */
.upload-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 14px;
  background: #ecfdf5;
  border: 1px solid #a7f3d0;
  border-radius: 8px;
  flex-wrap: wrap;
}
.upload-banner__info { display: flex; align-items: center; gap: 8px; min-width: 0; }
.upload-banner__icon { font-size: 16px; }
.upload-banner__name { font-size: 13px; font-weight: 600; color: #065f46; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 260px; }
.upload-banner__size { font-size: 12px; color: #6b7280; }
.upload-banner__actions { display: flex; align-items: center; gap: 8px; }
.upload-banner__clear { background: none; border: none; cursor: pointer; font-size: 16px; color: #6b7280; padding: 4px; }
.upload-banner__clear:hover { color: #1f2937; }

/* ═══ SELECTION BAR ═══ */
.selection-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 14px;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
  color: #1e40af;
}
.selection-bar__actions { display: flex; gap: 6px; }

/* ═══ BREADCRUMB ═══ */
.breadcrumb {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 13px;
  padding: 6px 0;
  flex-wrap: wrap;
}
.breadcrumb__sep { color: #9ca3af; font-size: 14px; }
.breadcrumb__item {
  background: none;
  border: none;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 6px;
  color: #059669;
  font-weight: 500;
  font-size: 13px;
  transition: background 0.15s;
}
.breadcrumb__item:hover:not(:disabled) { background: #ecfdf5; }
.breadcrumb__item--current { color: #374151; font-weight: 600; cursor: default; }
.breadcrumb__item--current:hover { background: transparent; }

/* ═══ FILE GRID ═══ */
.file-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 12px;
}
.file-card {
  position: relative;
  border: 1px solid var(--drive-border);
  border-radius: 10px;
  padding: 10px;
  cursor: pointer;
  transition: all 0.15s;
  background: white;
}
.file-card:hover { border-color: #a7f3d0; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.file-card--selected { border-color: #059669; background: #ecfdf5; box-shadow: 0 0 0 1px #059669; }
.file-card--drop-target { border-color: #059669; background: #d1fae5; }
.file-card__checkbox { position: absolute; top: 8px; right: 8px; z-index: 5; accent-color: #059669; }
.file-card__preview {
  width: 100%;
  aspect-ratio: 4/3;
  border-radius: 6px;
  overflow: hidden;
  background: #f3f4f6;
  display: flex;
  align-items: center;
  justify-content: center;
}
.file-card__thumb { width: 100%; height: 100%; object-fit: cover; }
.file-card__icon-wrap { display: flex; flex-direction: column; align-items: center; gap: 4px; }
.file-card__icon { font-size: 32px; }
.file-card__ext { font-size: 10px; font-weight: 700; color: #9ca3af; letter-spacing: 0.5px; }
.file-card__meta { margin-top: 8px; }
.file-card__name {
  font-size: 12px;
  font-weight: 600;
  color: #1f2937;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.file-card__detail { font-size: 11px; color: #9ca3af; margin-top: 2px; }

/* ═══ EMPTY STATE ═══ */
.empty-state {
  text-align: center;
  padding: 40px 20px;
  color: #9ca3af;
}
.empty-state__icon { font-size: 40px; display: block; margin-bottom: 8px; }
.empty-state p { font-size: 14px; margin: 0; }
.empty-state__hint { font-size: 12px; margin-top: 4px; }

/* ═══ RECYCLE ═══ */
.recycle-notice {
  font-size: 12px;
  color: #92400e;
  background: #fffbeb;
  border: 1px solid #fde68a;
  padding: 8px 12px;
  border-radius: 8px;
}
.recycle-list { display: flex; flex-direction: column; gap: 1px; background: #f3f4f6; border-radius: 8px; overflow: hidden; }
.recycle-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  background: white;
  gap: 12px;
}
.recycle-item__info { display: flex; align-items: center; gap: 10px; min-width: 0; }
.recycle-item__icon { font-size: 20px; flex-shrink: 0; }
.recycle-item__text { min-width: 0; }
.recycle-item__name { font-size: 13px; font-weight: 500; color: #1f2937; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.recycle-item__detail { font-size: 11px; color: #9ca3af; }
.recycle-item__actions { display: flex; gap: 6px; flex-shrink: 0; }

/* ═══ CONTEXT MENU ═══ */
.ctx-menu {
  position: fixed;
  z-index: 80;
  min-width: 180px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  box-shadow: 0 8px 24px rgba(0,0,0,0.12);
  padding: 4px;
  animation: ctxIn 0.1s ease;
}
@keyframes ctxIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
.ctx-menu__item {
  display: block;
  width: 100%;
  text-align: left;
  padding: 8px 12px;
  font-size: 13px;
  border: none;
  background: none;
  cursor: pointer;
  border-radius: 6px;
  color: #374151;
  transition: background 0.1s;
}
.ctx-menu__item:hover { background: #f0fdf4; }
.ctx-menu__item--danger { color: #dc2626; }
.ctx-menu__item--danger:hover { background: #fef2f2; }
.ctx-menu__divider { height: 1px; background: #e5e7eb; margin: 4px 0; }

/* ═══ DRAG OVERLAY ═══ */
.drag-overlay {
  position: absolute;
  inset: 0;
  background: rgba(5, 150, 105, 0.08);
  border-radius: var(--drive-radius);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 20;
  pointer-events: none;
}
.drag-overlay__content { text-align: center; color: #059669; }
.drag-overlay__icon { font-size: 36px; display: block; margin-bottom: 4px; }
.drag-overlay__content p { font-size: 14px; font-weight: 600; margin: 0; }

/* ═══ MODALS ═══ */
.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 85;
  background: rgba(0,0,0,0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  animation: fadeIn 0.15s;
}
.modal-overlay--dark { background: rgba(0,0,0,0.6); z-index: 50; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
.modal {
  background: white;
  border-radius: 12px;
  width: 100%;
  max-width: 400px;
  box-shadow: 0 16px 48px rgba(0,0,0,0.15);
  overflow: hidden;
}
.modal__title { font-size: 16px; font-weight: 600; color: #1f2937; padding: 16px 20px 0; margin: 0; }
.modal__message { font-size: 14px; color: #6b7280; padding: 12px 20px; margin: 0; }
.modal__input {
  display: block;
  width: calc(100% - 40px);
  margin: 12px 20px;
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 14px;
  outline: none;
  transition: border 0.15s;
}
.modal__input:focus { border-color: #059669; box-shadow: 0 0 0 2px rgba(5,150,105,0.15); }
.modal__footer { display: flex; justify-content: flex-end; gap: 8px; padding: 12px 20px; background: #f9fafb; border-top: 1px solid #f3f4f6; }

/* ═══ PREVIEW MODAL ═══ */
.preview-modal {
  width: 100%;
  max-width: 1200px;
  height: 85vh;
  background: white;
  border-radius: 12px;
  box-shadow: 0 16px 48px rgba(0,0,0,0.2);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}
.preview-modal__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  border-bottom: 1px solid #e5e7eb;
}
.preview-modal__name { font-size: 14px; font-weight: 600; color: #1f2937; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin: 0; }
.preview-modal__actions { display: flex; gap: 8px; flex-shrink: 0; }
.preview-modal__body { flex: 1; background: #f3f4f6; }
.preview-modal__img { width: 100%; height: 100%; object-fit: contain; }
.preview-modal__iframe { width: 100%; height: 100%; border: none; background: white; }

/* ═══ RESPONSIVE ═══ */
@media (max-width: 640px) {
  .file-grid { grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 8px; }
  .toolbar { flex-direction: column; align-items: stretch; }
  .toolbar__actions { flex-wrap: wrap; }
  .drive-panel { padding: 14px; }
}

/* ═══ UTILITIES ═══ */
.hidden { display: none; }
.text-red-600 { color: #dc2626; }
.font-semibold { font-weight: 600; }
</style>
