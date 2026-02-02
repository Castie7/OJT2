<script setup lang="ts">
import { useSubmittedResearches, type User } from '../composables/useSubmittedResearches' 

const props = defineProps<{
    currentUser: User | null
    isArchived: boolean
}>()

// 1. Define Emit for the parent to catch
const emit = defineEmits<{
  (e: 'edit', item: any): void
}>()

const {
    // State
    isLoading, searchQuery, 
    selectedResearch, commentModal, isSendingComment,
    chatContainer, confirmModal,
    
    // Computed
    filteredItems, paginatedItems, currentPage, totalPages, itemsPerPage,
    
    // Methods
    fetchData, nextPage, prevPage,
    requestArchive, executeArchive, openComments, postComment,
    // Removed internal edit methods (openEdit, saveEdit, etc.) since parent handles it now
    
    // Helpers
    getDeadlineStatus, 
    getArchiveDaysLeft,
    formatSimpleDate  // <--- ADDED AS REQUESTED
} = useSubmittedResearches(props)

defineExpose({ fetchData })
</script>

<template>
  <div class="flex flex-col h-full">
    
    <div class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-4">
      <div class="relative w-full sm:w-64">
        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">🔍</span>
        <input v-model="searchQuery" type="text" placeholder="Search..." class="w-full pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 text-sm"/>
      </div>
      <div class="text-xs text-gray-500">Showing {{ paginatedItems.length }} of {{ filteredItems.length }} items</div>
    </div>

    <div v-if="isLoading" class="text-center py-10 text-gray-400">Loading...</div>

    <div v-else class="flex flex-col min-h-[400px]">
      <div class="overflow-x-auto flex-1">
        <table class="min-w-full divide-y divide-gray-200 shadow-sm border border-gray-100 rounded-lg">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Title</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">
                {{ isArchived ? 'Auto-Delete In' : 'Timeline' }}
              </th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Crop Variation</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Status</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Review</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="item in paginatedItems" :key="item.id" class="hover:bg-green-50 transition">
              
              <td class="px-6 py-4 font-medium text-gray-900">{{ item.title }}</td>
              
              <td class="px-6 py-4">
                <div v-if="isArchived">
                  <span class="text-xs font-bold px-2 py-1 rounded bg-red-100 text-red-700 border border-red-200">
                    ⚠️ {{ getArchiveDaysLeft(item.archived_at) }} Days left
                  </span>
                </div>

                <div v-else-if="item.status === 'approved'">
                  <div class="mb-1">
                    <span class="px-2 py-0.5 text-xs font-bold rounded bg-green-100 text-green-700 border border-green-200">✅ Completed</span>
                  </div>
                  <div class="text-[11px] text-gray-500 flex flex-col gap-0.5">
                    <span>Submitted: <b class="text-gray-700">{{ formatSimpleDate(item.created_at) }}</b></span>
                    <span>Approved: <b class="text-green-700">{{ formatSimpleDate(item.approved_at || item.updated_at) }}</b></span>
                  </div>
                </div>
                
                <div v-else-if="item.deadline_date">
                  <span :class="`px-2 py-1 text-xs rounded font-bold ${getDeadlineStatus(item.deadline_date)?.color}`">
                    {{ getDeadlineStatus(item.deadline_date)?.text }}
                  </span>
                  <div class="text-[10px] text-gray-400 mt-1">
                    Submitted: {{ formatSimpleDate(item.created_at) }}
                  </div>
                </div>

                <span v-else class="text-gray-400 text-xs">No Deadline</span>
              </td>
              
              <td class="px-6 py-4">
                <span class="px-2 py-1 text-xs font-semibold rounded bg-amber-50 text-amber-700 border border-amber-100">
                  {{ item.crop_variation || 'Standard Variety' }}
                </span>
              </td>
              
              <td class="px-6 py-4">
                <span v-if="isArchived" class="px-2 py-1 text-xs font-bold rounded-full bg-red-100 text-red-700">Archived</span>
                <span v-else-if="item.status === 'pending'" class="px-2 py-1 text-xs font-bold rounded-full bg-yellow-100 text-yellow-800 border border-yellow-200">⏳ Pending Review</span>
                <span v-else-if="item.status === 'approved'" class="px-2 py-1 text-xs font-bold rounded-full bg-green-100 text-green-700 border border-green-200">✅ Published</span>
                <span v-else-if="item.status === 'rejected'" class="px-2 py-1 text-xs font-bold rounded-full bg-red-100 text-red-700 border border-red-200">❌ Rejected</span>
              </td>

              <td class="px-6 py-4">
                <button @click="openComments(item)" class="text-blue-600 hover:text-blue-800 text-sm font-bold flex items-center gap-1 transition-colors">💬 Comments</button>
              </td>

              <td class="px-6 py-4 text-right flex justify-end gap-2">
                <button v-if="item.status === 'approved' && !isArchived" @click="selectedResearch = item" class="text-xs px-3 py-1 rounded font-bold border text-blue-600 border-blue-200 hover:bg-blue-50 transition">View PDF</button>
                <template v-else>
                  
                  <button 
                    v-if="!isArchived" 
                    @click="emit('edit', item)" 
                    class="text-xs px-3 py-1 rounded font-bold border text-yellow-700 border-yellow-400 hover:bg-yellow-100 transition"
                  >
                    ✏️ Edit
                  </button>

                  <button @click="requestArchive(item)" :class="`text-xs px-3 py-1 rounded font-bold border transition ${isArchived ? 'text-green-600 border-green-200 hover:bg-green-100' : 'text-red-600 border-red-200 hover:bg-red-100'}`">
                    {{ isArchived ? '♻️ Restore' : '📦 Archive' }}
                  </button>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
        
        <div v-if="filteredItems.length === 0" class="text-center py-10 border border-dashed border-gray-300 rounded mt-4 text-gray-500">
           {{ isArchived ? 'No archived items.' : 'No submissions found.' }}
        </div>
      </div>
      
      <div v-if="filteredItems.length > itemsPerPage" class="mt-4 flex justify-between items-center border-t pt-4">
        <span class="text-sm text-gray-500">
          Showing {{ ((currentPage - 1) * itemsPerPage) + 1 }} to {{ Math.min(currentPage * itemsPerPage, filteredItems.length) }} of {{ filteredItems.length }} entries
        </span>
        <div class="flex gap-2">
          <button @click="prevPage" :disabled="currentPage === 1" class="px-3 py-1 text-sm font-bold rounded-lg border bg-white hover:bg-gray-50 disabled:opacity-50 transition">Previous</button>
          <span class="px-3 py-1 text-sm font-bold bg-green-50 text-green-700 rounded-lg border border-green-200">Page {{ currentPage }} of {{ totalPages }}</span>
          <button @click="nextPage" :disabled="currentPage === totalPages" class="px-3 py-1 text-sm font-bold rounded-lg border bg-white hover:bg-gray-50 disabled:opacity-50 transition">Next</button>
        </div>
      </div>
    </div>

    <div v-if="commentModal.show" class="modal-overlay"><div class="modal-content"><div class="modal-header"><h3>Review: {{ commentModal.title }}</h3><button @click="commentModal.show=false" class="close-btn">×</button></div><div class="modal-body"><div class="comments-list" ref="chatContainer"><TransitionGroup name="chat"><div v-for="c in commentModal.list" :key="c.id" :class="['comment-bubble', c.role==='admin'?'admin-msg':'user-msg']"><strong>{{ c.user_name }} ({{ c.role }}):</strong><p>{{ c.comment }}</p></div></TransitionGroup></div><div class="comment-input"><textarea v-model="commentModal.newComment" @keydown.enter.prevent="postComment"></textarea><button @click="postComment" :disabled="isSendingComment">Send</button></div></div></div></div>
    
    <div v-if="selectedResearch" class="modal-overlay"><div class="bg-white rounded-lg w-full max-w-4xl h-[90vh] flex flex-col"><div class="bg-green-800 text-white p-4 flex justify-between"><h2>{{ selectedResearch.title }}</h2><button @click="selectedResearch=null">&times;</button></div><div class="flex-1 bg-gray-100 p-4"><iframe :src="`http://localhost:8080/uploads/${selectedResearch.file_path}`" class="w-full h-full border-none"></iframe></div></div></div>
    
    <Transition name="pop"><div v-if="confirmModal.show" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60"><div class="bg-white rounded-2xl p-6 text-center"><h3>{{ confirmModal.title }}</h3><div class="flex gap-3 justify-center mt-4"><button @click="confirmModal.show=false" class="px-4 py-2 bg-gray-100 rounded" :disabled="confirmModal.isProcessing">Cancel</button><button @click="executeArchive" class="px-4 py-2 bg-green-600 text-white rounded" :disabled="confirmModal.isProcessing">Yes</button></div></div></div></Transition>

    </div>
</template>

<style scoped src="../assets/styles/SubmittedResearches.css"></style>