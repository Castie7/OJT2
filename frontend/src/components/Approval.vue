<script setup lang="ts">
import { useApproval, type User } from '../composables/useApproval'

const props = defineProps<{
  currentUser: User | null
}>()
//COmment ni Baron
const {
  activeTab, items, isLoading, selectedResearch, 
  currentPage, itemsPerPage, paginatedItems, totalPages, nextPage, prevPage,
  deadlineModal, commentModal, isSendingComment, chatContainer,
  fetchData, handleAction, formatDate, getDaysLeft,
  openDeadlineModal, saveNewDeadline, openComments, postComment
} = useApproval(props.currentUser)
</script>

<template>
  <div class="bg-white p-6 rounded-lg shadow-lg min-h-[500px]">
    
    <div class="mb-6 border-b pb-4 flex flex-col md:flex-row justify-between items-center gap-4">
      <div>
        <h2 class="text-xl font-bold text-gray-800">📋 Approvals & Rejections</h2>
        <p class="text-sm text-gray-500">Manage submissions and restore rejected items.</p>
      </div>
      <div class="flex bg-gray-100 p-1 rounded-lg">
        <button @click="activeTab = 'pending'; fetchData()" :class="`px-4 py-2 text-sm font-bold rounded-md transition ${activeTab === 'pending' ? 'bg-white text-green-700 shadow' : 'text-gray-500 hover:text-gray-700'}`">⏳ Pending</button>
        <button @click="activeTab = 'rejected'; fetchData()" :class="`px-4 py-2 text-sm font-bold rounded-md transition ${activeTab === 'rejected' ? 'bg-white text-red-600 shadow' : 'text-gray-500 hover:text-gray-700'}`">🗑️ Rejected Bin</button>
      </div>
    </div>

    <div v-if="isLoading" class="text-center py-10 text-gray-400">Loading...</div>

    <div v-else class="flex flex-col min-h-[400px]">
      <div class="overflow-x-auto flex-1">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Details</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">{{ activeTab === 'pending' ? 'Deadline' : 'Auto-Delete In' }}</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Review</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="item in paginatedItems" :key="item.id" class="hover:bg-green-50 transition">
              <td class="px-6 py-4">
                <div class="font-bold text-gray-900">{{ item.title }}</div>
                <div class="text-sm text-gray-500">By: {{ item.author }}</div>
                <button @click="selectedResearch = item" class="text-xs text-blue-600 font-bold hover:underline mt-1">📄 View PDF</button>
              </td>
              <td class="px-6 py-4">
                <div v-if="activeTab === 'pending'" class="flex items-center gap-2">
                  <span :class="`text-sm font-medium ${!item.deadline_date ? 'text-gray-400' : 'text-gray-700'}`">{{ formatDate(item.deadline_date) }}</span>
                  <button @click="openDeadlineModal(item)" class="text-gray-400 hover:text-green-600 transition" title="Extend Deadline">🕒</button>
                </div>
                <div v-else><span class="text-xs font-bold px-2 py-1 rounded bg-red-100 text-red-700 border border-red-200">⚠️ {{ getDaysLeft(item.rejected_at) }} Days left</span></div>
              </td>
              <td class="px-6 py-4"><button @click="openComments(item)" class="text-blue-600 hover:text-blue-800 text-sm font-bold flex items-center gap-1">💬 Comments</button></td>
              <td class="px-6 py-4 text-right space-x-2">
                <template v-if="activeTab === 'pending'">
                  <button @click="handleAction(item.id, 'approve')" class="text-xs bg-green-100 text-green-700 px-3 py-1 rounded font-bold hover:bg-green-200 transition">✅ Approve</button>
                  <button @click="handleAction(item.id, 'reject')" class="text-xs bg-red-100 text-red-700 px-3 py-1 rounded font-bold hover:bg-red-200 transition">❌ Reject</button>
                </template>
                <template v-else><button @click="handleAction(item.id, 'restore')" class="text-xs bg-blue-100 text-blue-700 px-3 py-1 rounded font-bold hover:bg-blue-200 transition">♻️ Restore to Pending</button></template>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="items.length === 0" class="text-center py-10 text-gray-500">{{ activeTab === 'pending' ? 'No pending items to review.' : 'The rejected bin is empty.' }}</div>
      </div>

      <div v-if="items.length > itemsPerPage" class="mt-4 flex justify-between items-center border-t pt-4">
        <span class="text-sm text-gray-500">Showing {{ ((currentPage - 1) * itemsPerPage) + 1 }} to {{ Math.min(currentPage * itemsPerPage, items.length) }} of {{ items.length }} entries</span>
        <div class="flex gap-2">
          <button @click="prevPage" :disabled="currentPage === 1" class="px-3 py-1 text-sm font-bold rounded-lg border bg-white hover:bg-gray-50 disabled:opacity-50 transition">Previous</button>
          <span class="px-3 py-1 text-sm font-bold bg-green-50 text-green-700 rounded-lg border border-green-200">Page {{ currentPage }} of {{ totalPages }}</span>
          <button @click="nextPage" :disabled="currentPage === totalPages" class="px-3 py-1 text-sm font-bold rounded-lg border bg-white hover:bg-gray-50 disabled:opacity-50 transition">Next</button>
        </div>
      </div>
    </div>

    <div v-if="deadlineModal.show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-75"><div class="bg-white rounded-lg w-full max-w-sm p-6 shadow-2xl"><h3 class="font-bold text-lg mb-2 text-gray-800">🕒 Extend Deadline</h3><p class="text-sm text-gray-500 mb-4">Set a new due date for: <br><b>{{ deadlineModal.title }}</b></p><input v-model="deadlineModal.newDate" type="date" class="w-full border p-2 rounded mb-6 focus:ring-2 focus:ring-green-500 outline-none"/><div class="flex justify-end gap-2"><button @click="deadlineModal.show = false" class="px-4 py-2 text-gray-500 font-bold hover:bg-gray-100 rounded">Cancel</button><button @click="saveNewDeadline" class="px-4 py-2 bg-green-600 text-white font-bold rounded hover:bg-green-700">Update Date</button></div></div></div>
    <div v-if="selectedResearch" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-75 backdrop-blur-sm"><div class="bg-white rounded-lg shadow-2xl w-full max-w-4xl h-[90vh] flex flex-col overflow-hidden"><div class="bg-green-800 text-white p-4 flex justify-between items-center shrink-0"><div><h2 class="text-xl font-bold leading-tight">{{ selectedResearch.title }}</h2><p class="text-green-200 text-sm">Author: {{ selectedResearch.author }}</p></div><button @click="selectedResearch = null" class="text-white hover:text-gray-300 text-3xl font-bold leading-none">&times;</button></div><div class="flex-1 overflow-y-auto bg-gray-100 p-4"><div v-if="selectedResearch.file_path" class="bg-white p-1 rounded shadow h-[600px]"><iframe :src="`http://localhost:8080/uploads/${selectedResearch.file_path}`" class="w-full h-full border-none rounded" title="PDF Viewer"></iframe></div></div></div></div>
    
    <div v-if="commentModal.show" class="modal-overlay">
      <div class="modal-content">
        <div class="modal-header"><h3>Review: {{ commentModal.title }}</h3><button @click="commentModal.show = false" class="close-btn">×</button></div>
        <div class="modal-body">
          <div class="comments-section">
            <h4 class="text-xs font-bold text-gray-500 uppercase mb-2">Revision History</h4>
            <div class="comments-list" ref="chatContainer">
              <TransitionGroup name="chat">
                <div v-for="msg in commentModal.list" :key="msg.id" :class="['comment-bubble', msg.role === 'admin' ? 'admin-msg' : 'user-msg']">
                  <strong>{{ msg.user_name }} ({{ msg.role }})</strong><p>{{ msg.comment }}</p><small>{{ new Date(msg.created_at).toLocaleString() }}</small>
                </div>
              </TransitionGroup>
              <p v-if="commentModal.list.length === 0" class="no-comments">No comments yet.</p>
            </div>
            <div class="comment-input">
              <textarea v-model="commentModal.newComment" placeholder="Write a reply..." @keydown.enter.prevent="postComment"></textarea>
              <button @click="postComment" :disabled="isSendingComment" :class="`btn btn-send ${isSendingComment ? 'opacity-50 cursor-not-allowed' : ''}`">{{ isSendingComment ? 'Sending...' : 'Send' }}</button>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<style scoped src="../assets/styles/Approval.css"></style>