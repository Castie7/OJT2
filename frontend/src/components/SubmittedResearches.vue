<script setup>
import { ref, onMounted, watch, nextTick, computed } from 'vue'

const props = defineProps(['currentUser', 'isArchived'])

// Data
const myItems = ref([])
const isLoading = ref(false)
const searchQuery = ref('') // <--- NEW: Search State

// --- PAGINATION STATE ---
const currentPage = ref(1)
const itemsPerPage = 10

// UI
const editingItem = ref(null)
const editPdfFile = ref(null)
const isSaving = ref(false)
const selectedResearch = ref(null)

// Modals
const commentModal = ref({ show: false, researchId: null, title: '', list: [], newComment: '' })
const isSendingComment = ref(false)
const chatContainer = ref(null)
const confirmModal = ref({ show: false, id: null, action: '', title: '', subtext: '', isProcessing: false })

// --- HELPERS ---
const getHeaders = () => {
  const token = document.cookie.split('; ').find(row => row.startsWith('auth_token='))?.split('=')[1];
  return { 'Authorization': token };
}

const getDeadlineStatus = (deadline) => {
  if (!deadline) return null;
  const today = new Date();
  const due = new Date(deadline);
  const diffTime = due - today;
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)); 
  if (diffDays < 0) return { text: `Overdue by ${Math.abs(diffDays)} days`, color: 'text-red-600 bg-red-100' };
  if (diffDays === 0) return { text: 'Due Today!', color: 'text-red-600 font-bold bg-red-100' };
  if (diffDays <= 7) return { text: `${diffDays} days left`, color: 'text-yellow-700 bg-yellow-100' };
  return { text: due.toLocaleDateString(), color: 'text-gray-500' };
}

const formatSimpleDate = (dateStr) => {
  if (!dateStr) return 'N/A';
  return new Date(dateStr).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

const getArchiveDaysLeft = (archivedDate) => {
  if (!archivedDate) return 60; 
  const start = new Date(archivedDate);
  const expiration = new Date(start);
  expiration.setDate(start.getDate() + 60); 
  const today = new Date();
  const diffTime = expiration - today;
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
  return diffDays > 0 ? diffDays : 0;
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
        currentPage.value = 1 
        searchQuery.value = '' // Reset search on tab change
    }
  } catch (error) { console.error("Error fetching items:", error) } 
  finally { isLoading.value = false }
}

defineExpose({ fetchData })
onMounted(() => fetchData())
watch(() => props.isArchived, () => fetchData())

// --- SEARCH & PAGINATION LOGIC ---

// 1. Filter Logic (Search)
const filteredItems = computed(() => {
  if (!searchQuery.value) return myItems.value;
  const query = searchQuery.value.toLowerCase();
  return myItems.value.filter(item => 
    item.title.toLowerCase().includes(query) || 
    item.author.toLowerCase().includes(query)
  );
})

// 2. Pagination Logic (Applied to filtered results)
const paginatedItems = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage
  const end = start + itemsPerPage
  return filteredItems.value.slice(start, end)
})

const totalPages = computed(() => Math.ceil(filteredItems.value.length / itemsPerPage))

// Reset page when searching
watch(searchQuery, () => { currentPage.value = 1 })

const nextPage = () => { if (currentPage.value < totalPages.value) currentPage.value++ }
const prevPage = () => { if (currentPage.value > 1) currentPage.value-- }

// --- ACTIONS ---
const requestArchive = (item) => {
  const action = props.isArchived ? 'Restore' : 'Archive';
  confirmModal.value = {
    show: true, id: item.id, action: action,
    title: action === 'Archive' ? 'Move to Trash?' : 'Restore File?',
    subtext: action === 'Archive' ? `Remove "${item.title}"?` : `Restore "${item.title}"?`,
    isProcessing: false
  }
}

const executeArchive = async () => {
  if (!confirmModal.value.id) return;
  confirmModal.value.isProcessing = true;
  try {
    const endpoint = props.isArchived 
        ? `http://localhost:8080/research/restore/${confirmModal.value.id}` 
        : `http://localhost:8080/research/archive/${confirmModal.value.id}`;

    const response = await fetch(endpoint, { method: 'POST', headers: getHeaders() })
    if (response.ok) {
        confirmModal.value.show = false; 
        fetchData(); 
    } else {
        alert("Action Failed.");
    }
  } catch (e) { alert("Network Error"); } 
  finally { confirmModal.value.isProcessing = false; }
}

// --- COMMENTS & EDIT ---
const openComments = async (item) => {
  commentModal.value = { show: true, researchId: item.id, title: item.title, list: [], newComment: '' }
  try {
    const res = await fetch(`http://localhost:8080/research/comments/${item.id}`, { headers: getHeaders() })
    if(res.ok) { commentModal.value.list = await res.json(); nextTick(() => { if (chatContainer.value) chatContainer.value.scrollTop = chatContainer.value.scrollHeight }); }
  } catch (e) {}
}

const postComment = async () => {
  if (isSendingComment.value || !commentModal.value.newComment.trim()) return
  isSendingComment.value = true
  try {
    await fetch('http://localhost:8080/research/comment', {
      method: 'POST', headers: { 'Content-Type': 'application/json', ...getHeaders() },
      body: JSON.stringify({ research_id: commentModal.value.researchId, user_id: props.currentUser.id, user_name: props.currentUser.name, role: 'user', comment: commentModal.value.newComment })
    })
    const refreshRes = await fetch(`http://localhost:8080/research/comments/${commentModal.value.researchId}`, { headers: getHeaders() })
    commentModal.value.list = await refreshRes.json(); commentModal.value.newComment = '' 
    nextTick(() => { if (chatContainer.value) chatContainer.value.scrollTop = chatContainer.value.scrollHeight });
  } catch (e) { alert("Failed: " + e.message); } finally { isSendingComment.value = false }
}

const openEdit = (item) => { editingItem.value = { ...item }; editPdfFile.value = null }
const handleEditFile = (e) => { 
  const file = e.target.files[0]
  if (!file) { editPdfFile.value = null; return; }
  const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
  if (!allowedExtensions.includes(file.name.split('.').pop().toLowerCase())) {
    alert("❌ Invalid File!"); e.target.value = ''; editPdfFile.value = null; return
  }
  editPdfFile.value = file 
}
const saveEdit = async () => {
  const item = editingItem.value;
  if (!item.title.trim() || !item.author.trim() || !item.deadline_date) { alert("⚠️ Missing Fields"); return; }
  isSaving.value = true
  const formData = new FormData()
  formData.append('title', item.title); formData.append('author', item.author);
  formData.append('abstract', item.abstract || ''); formData.append('start_date', item.start_date || '');
  formData.append('deadline_date', item.deadline_date);
  if (editPdfFile.value) formData.append('pdf_file', editPdfFile.value)

  try {
    const res = await fetch(`http://localhost:8080/research/update/${item.id}`, { method: 'POST', headers: getHeaders(), body: formData })
    if (res.ok) { alert("✅ Updated!"); editingItem.value = null; fetchData(); } else { alert("Update Failed"); }
  } catch (e) { alert("Server Error") } finally { isSaving.value = false }
}
</script>

<template>
  <div class="flex flex-col h-full">
    
    <div class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-4">
      <div class="relative w-full sm:w-64">
        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">🔍</span>
        <input 
          v-model="searchQuery" 
          type="text" 
          placeholder="Search..." 
          class="w-full pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 text-sm"
        />
      </div>
      <div class="text-xs text-gray-500">
        Showing {{ paginatedItems.length }} of {{ filteredItems.length }} items
      </div>
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
                    <div class="mb-1"><span class="px-2 py-0.5 text-xs font-bold rounded bg-green-100 text-green-700 border border-green-200">✅ Completed</span></div>
                    <div class="text-[11px] text-gray-500 flex flex-col gap-0.5">
                      <span>Started: <b>{{ formatSimpleDate(item.start_date) }}</b></span>
                      <span>Approved: <b>{{ formatSimpleDate(item.approved_at || item.updated_at) }}</b></span>
                    </div>
                </div>
                <div v-else-if="item.deadline_date">
                   <span :class="`px-2 py-1 text-xs rounded font-bold ${getDeadlineStatus(item.deadline_date).color}`">{{ getDeadlineStatus(item.deadline_date).text }}</span>
                   <div class="text-[10px] text-gray-400 mt-1">Started: {{ item.start_date || 'N/A' }}</div>
                </div>
                <span v-else class="text-gray-400 text-xs">No Deadline</span>
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
                  <button v-if="!isArchived" @click="openEdit(item)" class="text-xs px-3 py-1 rounded font-bold border text-yellow-700 border-yellow-400 hover:bg-yellow-100 transition">✏️ Edit</button>
                  <button @click="requestArchive(item)" :class="`text-xs px-3 py-1 rounded font-bold border transition ${isArchived ? 'text-green-600 border-green-200 hover:bg-green-100' : 'text-red-600 border-red-200 hover:bg-red-100'}`">
                    {{ isArchived ? '♻️ Restore' : '📦 Archive' }}
                  </button>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
        
        <div v-if="filteredItems.length === 0" class="text-center py-10 border border-dashed border-gray-300 rounded mt-4 text-gray-500">
           <span v-if="searchQuery">No results found for "{{ searchQuery }}".</span>
           <span v-else>{{ isArchived ? 'No archived items.' : 'No submissions found.' }}</span>
        </div>
      </div>

      <div v-if="filteredItems.length > itemsPerPage" class="mt-4 flex justify-between items-center border-t pt-4">
        <span class="text-sm text-gray-500">Showing {{ ((currentPage - 1) * itemsPerPage) + 1 }} to {{ Math.min(currentPage * itemsPerPage, filteredItems.length) }} of {{ filteredItems.length }}</span>
        <div class="flex gap-2">
          <button @click="prevPage" :disabled="currentPage === 1" class="px-3 py-1 text-sm font-bold rounded-lg border bg-white hover:bg-gray-50 disabled:opacity-50 transition">Previous</button>
          <span class="px-3 py-1 text-sm font-bold bg-green-50 text-green-700 rounded-lg border border-green-200">Page {{ currentPage }} of {{ totalPages }}</span>
          <button @click="nextPage" :disabled="currentPage === totalPages" class="px-3 py-1 text-sm font-bold rounded-lg border bg-white hover:bg-gray-50 disabled:opacity-50 transition">Next</button>
        </div>
      </div>
    </div>

    <div v-if="commentModal.show" class="modal-overlay"><div class="modal-content"><div class="modal-header"><h3>Review: {{ commentModal.title }}</h3><button @click="commentModal.show=false" class="close-btn">×</button></div><div class="modal-body"><div class="comments-list" ref="chatContainer"><TransitionGroup name="chat"><div v-for="c in commentModal.list" :key="c.id" :class="['comment-bubble', c.role==='admin'?'admin-msg':'user-msg']"><strong>{{ c.user_name }} ({{ c.role }}):</strong><p>{{ c.comment }}</p></div></TransitionGroup></div><div class="comment-input"><textarea v-model="commentModal.newComment" @keydown.enter.prevent="postComment"></textarea><button @click="postComment" :disabled="isSendingComment">Send</button></div></div></div></div>
    <div v-if="selectedResearch" class="modal-overlay"><div class="bg-white rounded-lg w-full max-w-4xl h-[90vh] flex flex-col"><div class="bg-green-800 text-white p-4 flex justify-between"><h2>{{ selectedResearch.title }}</h2><button @click="selectedResearch=null">&times;</button></div><div class="flex-1 bg-gray-100 p-4"><iframe :src="`http://localhost:8080/uploads/${selectedResearch.file_path}`" class="w-full h-full border-none"></iframe></div></div></div>
    <div v-if="editingItem" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75"><div class="bg-white rounded-lg w-full max-w-lg p-6"><h2 class="font-bold mb-4">Edit Research</h2><div class="space-y-4"><input v-model="editingItem.title" class="w-full border p-2"/><input v-model="editingItem.author" class="w-full border p-2"/><div class="grid grid-cols-2 gap-4"><input v-model="editingItem.start_date" type="date" class="border p-2"/><input v-model="editingItem.deadline_date" type="date" class="border p-2"/></div><textarea v-model="editingItem.abstract" class="w-full border p-2"></textarea><input type="file" @change="handleEditFile" class="w-full text-sm"/></div><div class="flex justify-end gap-2 mt-4"><button @click="editingItem=null">Cancel</button><button @click="saveEdit" class="bg-yellow-500 text-white px-4 py-2 rounded">Save</button></div></div></div>
    
    <Transition name="pop">
      <div v-if="confirmModal.show" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60">
        <div class="bg-white rounded-2xl p-6 text-center">
          <div class="mb-4 text-6xl animate-wiggle" v-if="confirmModal.action === 'Archive'">🗑️</div>
          <div class="mb-4 text-6xl animate-spin-slow" v-else>♻️</div>
          <h3 class="text-xl font-bold text-gray-900 mb-2">{{ confirmModal.title }}</h3>
          <p class="text-gray-500 text-sm mb-6">{{ confirmModal.subtext }}</p>
          <div class="flex gap-3 justify-center mt-4">
            <button @click="confirmModal.show = false" class="px-4 py-2 bg-gray-100 rounded hover:bg-gray-200 transition" :disabled="confirmModal.isProcessing">Cancel</button>
            <button @click="executeArchive" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition disabled:opacity-50" :disabled="confirmModal.isProcessing">{{ confirmModal.isProcessing ? 'Processing...' : 'Yes, Proceed' }}</button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
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
.btn-send { background: #007bff; color: white; padding: 0 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; transition: opacity 0.3s; }
.chat-enter-active, .chat-leave-active { transition: all 0.4s ease; }
.chat-enter-from, .chat-leave-to { opacity: 0; transform: translateY(20px); }
.pop-enter-active { animation: pop-in 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
.pop-leave-active { transition: opacity 0.2s ease; }
.pop-leave-to { opacity: 0; }
@keyframes pop-in { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
@keyframes wiggle { 0%, 100% { transform: rotate(0deg); } 25% { transform: rotate(-10deg); } 75% { transform: rotate(10deg); } }
.animate-wiggle { animation: wiggle 1s ease-in-out infinite; }
.animate-spin-slow { animation: spin 3s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>