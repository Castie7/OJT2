<script setup lang="ts">
import { computed } from 'vue'
import { useSubmittedResearches, type User } from '../composables/useSubmittedResearches' 

// ✅ USE THE ENV VARIABLE
// This automatically grabs the URL from your .env file
const ASSET_URL = import.meta.env.VITE_BACKEND_URL

const props = defineProps<{
  currentUser: User | null
  statusFilter: string
}>()

const isArchived = computed(() => props.statusFilter === 'archived')

// 1. Define Emit for the parent to catch
const emit = defineEmits<{
  (e: 'edit', item: any): void
  (e: 'view', item: any): void
}>()

const {
  // State
  myItems, 
  isLoading, searchQuery, 
  selectedResearch, commentModal, isSendingComment,
  chatContainer, confirmModal,
  
  // Computed
  filteredItems, paginatedItems, currentPage, totalPages, itemsPerPage,
  
  // Methods
  fetchData, nextPage, prevPage,
  requestArchive, executeArchive, openComments, postComment,
  
  // Helpers
  getDeadlineStatus, 
  getArchiveDaysLeft,
  formatSimpleDate
} = useSubmittedResearches(props)

// --- Handle Notification Click ---
const openNotification = async (researchId: number) => {
  // 1. Ensure data is loaded
  if (myItems.value.length === 0) {
      await fetchData()
  }
  
  // 2. Find the item
  const targetItem = myItems.value.find(i => i.id === researchId)
  
  // 3. Open it
  if (targetItem) {
    openComments(targetItem)
  }
}

// Expose functions to parent (MyWorkspace.vue)
defineExpose({ fetchData, openNotification })
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
            <tr v-for="item in paginatedItems" :key="item.id" class="hover:bg-green-50 transition cursor-pointer" @click="$emit('view', item)">
              
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
                <button @click.stop="openComments(item)" class="text-blue-600 hover:text-blue-800 text-sm font-bold flex items-center gap-1 transition-colors">💬 Comments</button>
              </td>

              <td class="px-6 py-4 text-right flex justify-end gap-2">
                <button v-if="item.status === 'approved' && !isArchived" @click.stop="selectedResearch = item" class="text-xs px-3 py-1 rounded font-bold border text-blue-600 border-blue-200 hover:bg-blue-50 transition">View PDF</button>
                
                <template v-else>
                  <button 
                    v-if="!isArchived" 
                    @click.stop="emit('edit', item)" 
                    class="text-xs px-3 py-1 rounded font-bold border text-yellow-700 border-yellow-400 hover:bg-yellow-100 transition"
                  >
                    ✏️ Edit
                  </button>

                  <button @click.stop="requestArchive(item)" :class="`text-xs px-3 py-1 rounded font-bold border transition ${isArchived ? 'text-green-600 border-green-200 hover:bg-green-100' : 'text-red-600 border-red-200 hover:bg-red-100'}`">
                    {{ isArchived ? '♻️ Restore' : '📦 Archive' }}
                  </button>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
        
        <div v-if="filteredItems.length === 0" class="text-center py-10 border border-dashed border-gray-300 rounded mt-4 text-gray-500">
           No {{ statusFilter }} items found.
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

    <Transition name="fade">
      <div v-if="commentModal.show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        
        <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl flex flex-col h-[600px] overflow-hidden transform transition-all">
          
          <div class="bg-white border-b px-6 py-4 flex justify-between items-center z-10">
            <div>
              <h3 class="font-bold text-gray-800 text-lg">Feedback & Review</h3>
              <p class="text-xs text-gray-500 truncate max-w-[250px]">Topic: {{ commentModal.title }}</p>
            </div>
            <button 
              @click="commentModal.show = false" 
              class="w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 hover:text-red-500 transition-colors"
            >
              <span class="text-xl leading-none">&times;</span>
            </button>
          </div>

          <div class="flex-1 bg-gray-50 overflow-y-auto p-4 custom-scrollbar" ref="chatContainer">
            <div v-if="commentModal.list.length === 0" class="h-full flex flex-col items-center justify-center text-gray-400 space-y-2">
              <span class="text-4xl">💬</span>
              <p class="text-sm">No comments yet. Start the conversation.</p>
            </div>

            <TransitionGroup name="message" tag="div" class="space-y-3">
              <div 
                v-for="c in commentModal.list" 
                :key="c.id" 
                class="flex flex-col max-w-[85%]"
                :class="c.role === 'user' ? 'self-end items-end ml-auto' : 'self-start items-start'"
              >
                <span class="text-[10px] text-gray-400 mb-1 px-1">
                  {{ c.user_name }} <span v-if="c.role === 'user'" class="text-green-600 font-bold">(You)</span>
                </span>
                
                <div 
                  class="px-4 py-2.5 shadow-sm text-sm break-words relative"
                  :class="c.role === 'user' 
                    ? 'bg-green-600 text-white rounded-2xl rounded-tr-none' 
                    : 'bg-white text-gray-800 rounded-2xl rounded-tl-none border border-gray-100'"
                >
                  <p>{{ c.comment }}</p>
                </div>
              </div>
            </TransitionGroup>
          </div>

          <div class="bg-white border-t p-4">
            <div class="relative flex items-end gap-2 bg-gray-100 rounded-xl p-2 border border-transparent focus-within:border-green-300 focus-within:ring-2 focus-within:ring-green-100 transition-all">
              <textarea 
                v-model="commentModal.newComment" 
                @keydown.enter.prevent="postComment" 
                placeholder="Type your reply..." 
                class="w-full bg-transparent border-none focus:ring-0 text-sm resize-none max-h-32 text-gray-700 placeholder-gray-400 py-2 pl-2"
                rows="1"
                style="min-height: 44px;"
              ></textarea>
              
              <button 
                @click="postComment" 
                :disabled="isSendingComment || !commentModal.newComment.trim()"
                class="mb-1 p-2 rounded-full flex-shrink-0 transition-all duration-300 ease-in-out"
                :class="isSendingComment || !commentModal.newComment.trim() 
                  ? 'bg-gray-300 cursor-not-allowed text-gray-500' 
                  : 'bg-green-600 hover:bg-green-700 text-white shadow-md hover:scale-105 active:scale-95'"
              >
                <svg v-if="isSendingComment" class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>

                <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                  <path d="M3.478 2.405a.75.75 0 00-.926.94l2.432 7.905H13.5a.75.75 0 010 1.5H4.984l-2.432 7.905a.75.75 0 00.926.94 60.519 60.519 0 0018.445-8.986.75.75 0 000-1.218A60.517 60.517 0 003.478 2.405z" />
                </svg>
              </button>
            </div>
            <div class="text-[10px] text-gray-400 mt-2 text-right">Press Enter to send</div>
          </div>

        </div>
      </div>
    </Transition>
    
    <div v-if="selectedResearch" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75 backdrop-blur-sm p-4" @click.self="selectedResearch=null">
        <div class="bg-white rounded-lg w-full max-w-4xl h-[90vh] flex flex-col shadow-2xl overflow-hidden">
            <div class="bg-green-800 text-white p-4 flex justify-between items-center">
                <h2 class="font-bold text-lg">{{ selectedResearch.title }}</h2>
                <button @click="selectedResearch=null" class="text-2xl font-bold hover:text-gray-300">&times;</button>
            </div>
            <div class="flex-1 bg-gray-100 p-4 relative">
                <iframe 
                  :src="`${ASSET_URL}/uploads/${selectedResearch.file_path}`" 
                  class="w-full h-full border-none bg-white rounded shadow-sm"
                  title="PDF Viewer"
                ></iframe>
            </div>
        </div>
    </div>
    
    <Transition name="pop">
      <div v-if="confirmModal.show" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm">
        <div class="bg-white rounded-2xl p-6 text-center w-full max-w-sm shadow-2xl transform transition-all">
          <div class="mb-4 text-5xl">{{ isArchived ? '♻️' : '🗑️' }}</div>
          <h3 class="text-xl font-bold text-gray-900 mb-2">{{ confirmModal.title }}</h3>
          <p class="text-gray-500 text-sm mb-6">{{ confirmModal.subtext }}</p>
          <div class="flex gap-3 justify-center">
            <button @click="confirmModal.show=false" class="px-5 py-2 bg-gray-100 text-gray-700 font-bold rounded-lg hover:bg-gray-200 transition" :disabled="confirmModal.isProcessing">Cancel</button>
            <button 
                @click="executeArchive" 
                class="px-5 py-2 text-white font-bold rounded-lg shadow-lg transition" 
                :class="isArchived ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'"
                :disabled="confirmModal.isProcessing"
            >
                Yes, {{ isArchived ? 'Restore' : 'Archive' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>

  </div>
</template>

<style scoped src="../assets/styles/SubmittedResearches.css"></style>