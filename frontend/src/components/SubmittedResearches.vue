<script setup>
import { ref, onMounted, watch, nextTick } from 'vue'

const props = defineProps(['currentUser', 'isArchived'])

// Data States
const myItems = ref([])
const isLoading = ref(false)

// UI States
const editingItem = ref(null)
const editPdfFile = ref(null)
const isSaving = ref(false)
const selectedResearch = ref(null) // For PDF Viewer

// Modals
const commentModal = ref({ show: false, researchId: null, title: '', list: [], newComment: '' })
const confirmModal = ref({ show: false, id: null, action: '', title: '', subtext: '' })
const chatContainer = ref(null)

// --- HELPERS ---
const getHeaders = () => {
  const token = document.cookie.split('; ').find(row => row.startsWith('auth_token='))?.split('=')[1];
  return { 'Authorization': token };
}

// --- FETCH DATA ---
const fetchData = async () => {
  isLoading.value = true
  try {
    const endpoint = props.isArchived 
      ? 'http://localhost:8080/research/my-archived' 
      : 'http://localhost:8080/research/my-submissions';

    const response = await fetch(endpoint, { headers: getHeaders() })
    if (response.ok) {
      myItems.value = await response.json()
    }
  } catch (error) {
    console.error("Error fetching items:", error)
  } finally {
    isLoading.value = false
  }
}

defineExpose({ fetchData })

onMounted(() => fetchData())
watch(() => props.isArchived, () => fetchData())

// --- ACTIONS ---
const requestArchive = (item) => {
  const action = props.isArchived ? 'Restore' : 'Archive';
  confirmModal.value = {
    show: true, id: item.id, action: action,
    title: action === 'Archive' ? 'Move to Trash?' : 'Restore File?',
    subtext: action === 'Archive' ? `Remove "${item.title}"?` : `Restore "${item.title}" to active list?`
  }
}
const executeArchive = async () => {
  if (!confirmModal.value.id) return;
  try {
    await fetch(`http://localhost:8080/research/archive/${confirmModal.value.id}`, { method: 'POST', headers: getHeaders() })
    confirmModal.value.show = false
    fetchData() 
  } catch (e) { alert("Error updating status") }
}

// --- COMMENTS LOGIC ---
const openComments = async (item) => {
  commentModal.value = { show: true, researchId: item.id, title: item.title, list: [], newComment: '' }
  try {
    const res = await fetch(`http://localhost:8080/research/comments/${item.id}`, { headers: getHeaders() })
    if(res.ok) { 
        commentModal.value.list = await res.json(); 
        nextTick(() => { if (chatContainer.value) chatContainer.value.scrollTop = chatContainer.value.scrollHeight });
    }
  } catch (e) { console.error("Error loading comments") }
}

const postComment = async () => {
  if (!commentModal.value.newComment.trim()) return
  try {
    await fetch('http://localhost:8080/research/comment', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...getHeaders() },
      body: JSON.stringify({
        research_id: commentModal.value.researchId,
        user_id: props.currentUser.id,
        user_name: props.currentUser.name,
        role: 'user',
        comment: commentModal.value.newComment
      })
    })
    const refreshRes = await fetch(`http://localhost:8080/research/comments/${commentModal.value.researchId}`, { headers: getHeaders() })
    commentModal.value.list = await refreshRes.json()
    commentModal.value.newComment = '' 
    nextTick(() => { if (chatContainer.value) chatContainer.value.scrollTop = chatContainer.value.scrollHeight });
  } catch (e) { alert("Failed: " + e.message); }
}

// --- EDIT LOGIC ---
const openEdit = (item) => { editingItem.value = { ...item }; editPdfFile.value = null }
const handleFile = (e) => { editPdfFile.value = e.target.files[0] }
const saveEdit = async () => {
  isSaving.value = true
  const formData = new FormData()
  formData.append('title', editingItem.value.title)
  formData.append('author', editingItem.value.author)
  formData.append('abstract', editingItem.value.abstract)
  if (editPdfFile.value) formData.append('pdf_file', editPdfFile.value)

  try {
    const res = await fetch(`http://localhost:8080/research/update/${editingItem.value.id}`, {
      method: 'POST', headers: getHeaders(), body: formData 
    })
    if (res.ok) { alert("Updated!"); editingItem.value = null; fetchData() }
  } catch (e) { alert("Server Error") } finally { isSaving.value = false }
}
</script>

<template>
  <div>
    <div v-if="isLoading" class="text-center py-10 text-gray-400">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-green-600 mx-auto mb-2"></div>
      Loading...
    </div>

    <div v-else class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200 shadow-sm border border-gray-100 rounded-lg">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Title</th>
            <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Status</th>
            <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Review</th>
            <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="item in myItems" :key="item.id" class="hover:bg-green-50 transition">
            <td class="px-6 py-4 font-medium text-gray-900">{{ item.title }}</td>
            
            <td class="px-6 py-4">
              <span v-if="isArchived" class="px-2 py-1 text-xs font-bold rounded-full bg-red-100 text-red-700">Archived</span>
              <span v-else-if="item.status === 'pending'" class="px-2 py-1 text-xs font-bold rounded-full bg-yellow-100 text-yellow-800 border border-yellow-200">⏳ Pending Review</span>
              <span v-else-if="item.status === 'approved'" class="px-2 py-1 text-xs font-bold rounded-full bg-green-100 text-green-700 border border-green-200">✅ Published</span>
              <span v-else-if="item.status === 'rejected'" class="px-2 py-1 text-xs font-bold rounded-full bg-red-100 text-red-700 border border-red-200">❌ Rejected</span>
            </td>

            <td class="px-6 py-4">
              <button @click="openComments(item)" class="text-blue-600 hover:text-blue-800 text-sm font-bold flex items-center gap-1 transition-colors">
                💬 Comments
              </button>
            </td>

            <td class="px-6 py-4 text-right flex justify-end gap-2">
              
              <button 
                v-if="item.status === 'approved' && !isArchived" 
                @click="selectedResearch = item"
                class="text-xs px-3 py-1 rounded font-bold border text-blue-600 border-blue-200 hover:bg-blue-50 transition"
              >
                View PDF
              </button>

              <template v-else>
                <button 
                    v-if="!isArchived" 
                    @click="openEdit(item)" 
                    class="text-xs px-3 py-1 rounded font-bold border text-yellow-700 border-yellow-400 hover:bg-yellow-100 transition"
                >
                    ✏️ Edit
                </button>
                
                <button 
                    @click="requestArchive(item)" 
                    :class="`text-xs px-3 py-1 rounded font-bold border transition ${isArchived ? 'text-green-600 border-green-200 hover:bg-green-100' : 'text-red-600 border-red-200 hover:bg-red-100'}`"
                >
                    {{ isArchived ? '♻️ Restore' : '📦 Archive' }}
                </button>
              </template>

            </td>
          </tr>
        </tbody>
      </table>
      
      <div v-if="myItems.length === 0" class="text-center py-10 border border-dashed border-gray-300 rounded mt-4 text-gray-500">
        {{ isArchived ? 'No archived items.' : 'No submissions found.' }}
      </div>
    </div>

    <div v-if="commentModal.show" class="modal-overlay">
      <div class="modal-content">
        <div class="modal-header">
          <h3>Review: {{ commentModal.title }}</h3>
          <button @click="commentModal.show = false" class="close-btn">×</button>
        </div>
        <div class="modal-body">
          <div class="comments-section">
            <h4 class="text-xs font-bold text-gray-500 uppercase mb-2">Revision History</h4>
            <div class="comments-list" ref="chatContainer">
              <div v-for="c in commentModal.list" :key="c.id" :class="['comment-bubble', c.role === 'admin' ? 'admin-msg' : 'user-msg']">
                <strong>{{ c.user_name }} ({{ c.role }}):</strong>
                <p>{{ c.comment }}</p>
                <small>{{ new Date(c.created_at).toLocaleString() }}</small>
              </div>
              <p v-if="commentModal.list.length === 0" class="no-comments">No comments yet.</p>
            </div>
            <div class="comment-input">
              <textarea v-model="commentModal.newComment" placeholder="Write a reply..."></textarea>
              <button @click="postComment" class="btn btn-send">Send Reply</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="selectedResearch" class="modal-overlay">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-4xl h-[90vh] flex flex-col overflow-hidden">
          <div class="bg-green-800 text-white p-4 flex justify-between items-center shrink-0">
            <div><h2 class="text-xl font-bold leading-tight">{{ selectedResearch.title }}</h2><p class="text-green-200 text-sm">Author: {{ selectedResearch.author }}</p></div>
            <button @click="selectedResearch = null" class="text-white hover:text-gray-300 text-3xl font-bold leading-none">&times;</button>
          </div>
          <div class="flex-1 overflow-y-auto bg-gray-100 p-4">
             <div v-if="selectedResearch.file_path" class="bg-white p-1 rounded shadow h-[600px]"><iframe :src="`http://localhost:8080/uploads/${selectedResearch.file_path}`" class="w-full h-full border-none rounded" title="PDF Viewer"></iframe></div>
             <div v-else class="flex flex-col items-center justify-center h-64 bg-white rounded shadow text-gray-400"><span class="text-4xl mb-2">📄</span><p>No PDF file attached.</p></div>
          </div>
        </div>
    </div>

    <div v-if="editingItem" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75">
      <div class="bg-white rounded-lg w-full max-w-lg p-6 shadow-2xl">
        <h2 class="font-bold text-lg mb-4">Edit Research</h2>
        <div class="space-y-4">
            <input v-model="editingItem.title" class="w-full border p-2 rounded" />
            <input v-model="editingItem.author" class="w-full border p-2 rounded" />
            <textarea v-model="editingItem.abstract" class="w-full border p-2 rounded" rows="3"></textarea>
            <input type="file" @change="handleFile" class="w-full text-sm" accept="application/pdf" />
        </div>
        <div class="flex justify-end gap-2 mt-4">
            <button @click="editingItem = null" class="text-gray-500 font-bold px-4">Cancel</button>
            <button @click="saveEdit" :disabled="isSaving" class="bg-yellow-500 text-green-900 px-6 py-2 rounded font-bold">
                {{ isSaving ? 'Saving...' : 'Save Changes' }}
            </button>
        </div>
      </div>
    </div>

    <Transition name="pop">
      <div v-if="confirmModal.show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-60 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden transform transition-all p-6 text-center">
          <div class="mb-4 flex justify-center">
            <div v-if="confirmModal.action === 'Archive'" class="text-6xl animate-wiggle">🗑️</div>
            <div v-else class="text-6xl animate-spin-slow">♻️</div>
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-2">{{ confirmModal.title }}</h3>
          <p class="text-gray-500 text-sm mb-6">{{ confirmModal.subtext }}</p>
          <div class="flex gap-3 justify-center">
            <button @click="confirmModal.show = false" class="px-5 py-2.5 rounded-xl font-bold text-gray-600 bg-gray-100 hover:bg-gray-200 transition">Cancel</button>
            <button @click="executeArchive" :class="`px-5 py-2.5 rounded-xl font-bold text-white shadow-lg transform active:scale-95 transition ${confirmModal.action === 'Archive' ? 'bg-red-500 hover:bg-red-600 shadow-red-200' : 'bg-green-600 hover:bg-green-700 shadow-green-200'}`">Yes, {{ confirmModal.action }}</button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
/* Modal & Chat */
.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; justify-content: center; align-items: center; z-index: 1000;}
.modal-content { background: white; width: 600px; padding: 20px; border-radius: 8px; max-height: 80vh; overflow-y: auto; display: flex; flex-direction: column; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
.modal-header { display: flex; justify-content: space-between; border-bottom: 1px solid #eee; margin-bottom: 15px; align-items: center; }
.close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #666; }

.comments-list { background: #f9f9f9; padding: 10px; height: 250px; overflow-y: auto; border: 1px solid #ddd; margin-bottom: 10px; border-radius: 4px; }
.comment-bubble { padding: 10px; margin-bottom: 10px; border-radius: 6px; font-size: 0.9em; max-width: 85%; }
.admin-msg { background: #e2e6ea; border-left: 4px solid #17a2b8; margin-right: auto; }
.user-msg { background: #fff3cd; text-align: right; border-right: 4px solid #ffc107; margin-left: auto; }

.comment-input { display: flex; gap: 10px; }
.comment-input textarea { width: 100%; height: 50px; padding: 8px; border: 1px solid #ccc; border-radius: 4px; resize: none; outline: none; }
.btn-send { background: #007bff; color: white; padding: 0 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }

/* Animations */
.pop-enter-active { animation: pop-in 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
.pop-leave-active { transition: opacity 0.2s ease; }
.pop-leave-to { opacity: 0; }
@keyframes pop-in { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
@keyframes wiggle { 0%, 100% { transform: rotate(0deg); } 25% { transform: rotate(-10deg); } 75% { transform: rotate(10deg); } }
.animate-wiggle { animation: wiggle 1s ease-in-out infinite; }
.animate-spin-slow { animation: spin 3s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>