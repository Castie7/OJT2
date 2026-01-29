<script setup>
import { ref, onMounted, watch, nextTick } from 'vue'

const props = defineProps(['currentUser'])

// Data States
const myResearches = ref([])
const isLoading = ref(false)
const viewMode = ref('list')
const showArchived = ref(false)

// Edit States
const editingResearch = ref(null)
const isSaving = ref(false)
const editPdfFile = ref(null)

// Refs for UI
const chatContainer = ref(null) // <--- NEW: Reference for auto-scrolling

// Archive Confirmation Modal State
const confirmModal = ref({
  show: false, id: null, action: '', title: '', subtext: ''
})

// Comments / Revision State
const commentModal = ref({
  show: false, researchId: null, title: '', list: [], newComment: ''
})

// --- FETCH DATA ---
const fetchMyData = async () => {
  isLoading.value = true
  try {
    const endpoint = showArchived.value 
      ? 'http://localhost:8080/research/archived' 
      : 'http://localhost:8080/research'

    const response = await fetch(endpoint)
    if (response.ok) {
      const allData = await response.json()
      // robust check: handle if currentUser is an object or just an ID
      const currentUserId = props.currentUser.id || props.currentUser
      myResearches.value = allData.filter(item => item.uploaded_by == currentUserId)
    }
  } catch (error) {
    console.error("Error fetching workspace:", error)
  } finally {
    isLoading.value = false
  }
}

watch(showArchived, () => { fetchMyData() })
onMounted(() => { fetchMyData() })

// --- ARCHIVE LOGIC ---
const requestArchiveToggle = (item) => {
  const action = showArchived.value ? 'Restore' : 'Archive';
  confirmModal.value = {
    show: true,
    id: item.id,
    action: action,
    title: action === 'Archive' ? 'Move to Trash?' : 'Restore Research?',
    subtext: action === 'Archive' 
      ? `Are you sure you want to remove "${item.title}"?` 
      : `This will make "${item.title}" visible again.`
  }
}

const executeArchiveToggle = async () => {
  if (!confirmModal.value.id) return;
  try {
    await fetch(`http://localhost:8080/research/archive/${confirmModal.value.id}`, { method: 'POST' })
    confirmModal.value.show = false
    fetchMyData() 
  } catch (error) { alert("Error updating item status") }
}

// --- EDIT LOGIC ---
const openEditModal = (item) => {
  editingResearch.value = { ...item } 
  editPdfFile.value = null 
}

const handleEditFileUpload = (event) => {
  editPdfFile.value = event.target.files[0]
}

const saveChanges = async () => {
  if (!editingResearch.value.title || !editingResearch.value.author) {
    alert("Title and Author are required.")
    return
  }
  isSaving.value = true
  
  const formData = new FormData()
  formData.append('title', editingResearch.value.title)
  formData.append('author', editingResearch.value.author)
  formData.append('abstract', editingResearch.value.abstract)
  if (editPdfFile.value) formData.append('pdf_file', editPdfFile.value)

  try {
    const response = await fetch(`http://localhost:8080/research/update/${editingResearch.value.id}`, {
      method: 'POST', body: formData 
    })
    const result = await response.json()
    if (result.status === 'success') {
      alert("Updated Successfully!")
      editingResearch.value = null 
      fetchMyData() 
    } else { alert("Error: " + result.message) }
  } catch (error) { alert("Server Error") } finally { isSaving.value = false }
}

// --- COMMENTS LOGIC ---

// 1. Open Modal & Fetch Comments
const openComments = async (item) => {
  commentModal.value.researchId = item.id
  commentModal.value.title = item.title
  commentModal.value.show = true
  commentModal.value.newComment = ''
  commentModal.value.list = [] 
  
  try {
    const res = await fetch(`http://localhost:8080/research/comments/${item.id}`)
    if(res.ok) {
      commentModal.value.list = await res.json()
      scrollToBottom()
    }
  } catch (e) { console.error("Error loading comments") }
}

// Helper to scroll chat to bottom
const scrollToBottom = () => {
  nextTick(() => {
    if (chatContainer.value) {
      chatContainer.value.scrollTop = chatContainer.value.scrollHeight
    }
  })
}

// 2. Post a New Comment (IMPROVED ERROR HANDLING)
const postComment = async () => {
  if (!commentModal.value.newComment.trim()) return

  // Determine ID and Name safely
  const userId = props.currentUser.id || props.currentUser;
  // If props.currentUser is just an ID string, fallback to 'Author'
  const userName = props.currentUser.name || 'Author';

  const payload = {
    research_id: commentModal.value.researchId,
    user_id: userId,
    user_name: userName,
    role: 'user', 
    comment: commentModal.value.newComment
  }

  try {
    const res = await fetch('http://localhost:8080/research/comment', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
    
    // 1. Get the JSON response first
    const data = await res.json();

    // 2. Check for server errors (400, 403, 500)
    if (!res.ok) {
      // Throw the actual message from the server
      throw new Error(data.messages?.error || data.message || "Unknown Server Error");
    }

    // 3. Success
    // Refresh the list
    const refreshRes = await fetch(`http://localhost:8080/research/comments/${commentModal.value.researchId}`)
    commentModal.value.list = await refreshRes.json()
    commentModal.value.newComment = '' 
    scrollToBottom()

  } catch (e) { 
    console.error(e);
    alert("Failed: " + e.message); // <--- Alerts the REAL error now
  }
}
</script>

<template>
  <div class="bg-white p-6 rounded-lg shadow-lg min-h-[500px]">
    
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 border-b pb-4 gap-4">
      <div>
        <h2 class="text-xl font-bold text-gray-800">
          {{ showArchived ? '🗑️ My Archived Files' : '👤 My Workspace' }}
        </h2>
        <p class="text-sm text-gray-500">Managing uploads for: <span class="font-bold text-green-700">{{ currentUser }}</span></p>
      </div>

      <div class="flex items-center gap-3">
        <button @click="showArchived = !showArchived" :class="`px-4 py-2 text-sm font-bold rounded-md border transition whitespace-nowrap ${showArchived ? 'bg-red-100 text-red-700 border-red-300' : 'bg-gray-100 text-gray-600 border-gray-200 hover:bg-gray-200'}`">
          {{ showArchived ? '← Back to Active' : 'View Archive' }}
        </button>
        <div class="flex bg-gray-100 p-1 rounded-lg">
          <button @click="viewMode = 'list'" :class="`px-3 py-1 text-sm font-medium rounded-md transition ${viewMode === 'list' ? 'bg-white text-green-700 shadow' : 'text-gray-500 hover:text-gray-700'}`">📃</button>
          <button @click="viewMode = 'grid'" :class="`px-3 py-1 text-sm font-medium rounded-md transition ${viewMode === 'grid' ? 'bg-white text-green-700 shadow' : 'text-gray-500 hover:text-gray-700'}`">🔲</button>
        </div>
      </div>
    </div>

    <div v-if="isLoading" class="flex flex-col items-center justify-center py-20 text-gray-400">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-green-600 mb-3"></div>
      <p>Loading your workspace...</p>
    </div>

    <div v-else>
      <div v-if="viewMode === 'list'" class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Title</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Status</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Manage</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="item in myResearches" :key="item.id" @click="selectedResearch = item" class="hover:bg-green-50 transition cursor-pointer">
              <td class="px-6 py-4 font-medium text-gray-900">{{ item.title }}</td>
              <td class="px-6 py-4">
                <span :class="`px-2 py-1 text-xs font-bold rounded-full ${showArchived ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'}`">
                  {{ showArchived ? 'Archived' : 'Published' }}
                </span>
              </td>
              <td class="px-6 py-4 text-right flex justify-end gap-2">
                <button v-if="!showArchived" @click.stop="openEditModal(item)" class="text-xs px-3 py-1 rounded font-bold border text-yellow-700 border-yellow-400 hover:bg-yellow-100 transition">✏️ Edit</button>
                
                <button @click.stop="requestArchiveToggle(item)" :class="`text-xs px-3 py-1 rounded font-bold border transition ${showArchived ? 'text-green-600 border-green-200 hover:bg-green-100' : 'text-red-600 border-red-200 hover:bg-red-100'}`">
                  {{ showArchived ? '♻️ Restore' : '📦 Archive' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div v-for="item in myResearches" :key="item.id" @click="selectedResearch = item" class="bg-gray-50 hover:bg-white border border-gray-200 hover:border-green-400 rounded-xl p-4 flex flex-col relative transition shadow hover:shadow-lg cursor-pointer">
          <div class="h-24 bg-gray-200 rounded-lg mb-3 flex items-center justify-center text-gray-400 group-hover:text-green-600"><span class="text-3xl">👤</span></div>
          <h3 class="font-bold text-gray-900">{{ item.title }}</h3>
          <div class="mt-4 flex gap-2">
            <button v-if="!showArchived" @click.stop="openEditModal(item)" class="flex-1 py-2 rounded font-bold border text-yellow-700 border-yellow-400 hover:bg-yellow-100 text-xs transition">✏️ Edit</button>
            <button 
                @click="openComments(item)" 
                class="ml-2 text-blue-600 hover:text-blue-800 text-sm font-medium"
              >
                💬 Revisions
              </button>
            <button @click.stop="requestArchiveToggle(item)" :class="`flex-1 py-2 rounded font-bold border text-xs transition ${showArchived ? 'text-green-600 border-green-200 hover:bg-green-100' : 'text-red-600 border-red-200 hover:bg-red-100'}`">
              {{ showArchived ? '♻️ Restore' : '📦 Archive' }}
            </button>
          </div>
        </div>
      </div>
      
      <div v-if="myResearches.length === 0" class="text-center py-12 bg-gray-50 rounded-lg border border-dashed border-gray-300 mt-4">
        <p class="text-gray-500 mb-2">{{ showArchived ? 'Your archive is empty.' : "You haven't uploaded anything yet." }}</p>
      </div>
    </div>

    <Transition name="fade">
      <div v-if="selectedResearch" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-75 backdrop-blur-sm">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-4xl h-[90vh] flex flex-col overflow-hidden" @click.stop>
          <div class="bg-green-800 text-white p-4 flex justify-between items-center shrink-0">
            <div><h2 class="text-xl font-bold leading-tight">{{ selectedResearch.title }}</h2><p class="text-green-200 text-sm">Author: {{ selectedResearch.author }}</p></div>
            <button @click="selectedResearch = null" class="text-white hover:text-gray-300 text-3xl font-bold leading-none">&times;</button>
          </div>
          <div class="flex-1 overflow-y-auto bg-gray-100 p-4">
            <div class="bg-white p-4 rounded shadow mb-4"><h3 class="text-sm font-bold text-gray-500 uppercase mb-2">Abstract</h3><p class="text-gray-800 leading-relaxed whitespace-pre-line text-sm">{{ selectedResearch.abstract || "No abstract provided." }}</p></div>
            <div v-if="selectedResearch.file_path" class="bg-white p-1 rounded shadow h-[600px]"><iframe :src="`http://localhost:8080/uploads/${selectedResearch.file_path}`" class="w-full h-full border-none rounded" title="PDF Viewer"></iframe></div>
          </div>
          <div class="bg-gray-50 p-4 border-t flex justify-between items-center shrink-0">
            <button @click="selectedResearch = null" class="bg-gray-800 hover:bg-gray-700 text-white px-6 py-2 rounded font-bold transition">Close</button>
          </div>
        </div>
      </div>
    </Transition>

    <Transition name="fade">
      <div v-if="editingResearch" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-75 backdrop-blur-sm">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-lg overflow-hidden" @click.stop>
          <div class="bg-yellow-500 text-green-900 p-4 flex justify-between items-center">
            <h2 class="text-lg font-bold">✏️ Edit Research Details</h2>
            <button @click="editingResearch = null" class="text-green-900 hover:text-white text-2xl font-bold">&times;</button>
          </div>
          <div class="p-6 space-y-4">
            <div><label class="block text-sm font-bold text-gray-700 mb-1">Title</label><input v-model="editingResearch.title" type="text" class="w-full border p-2 rounded focus:ring-2 focus:ring-yellow-500 outline-none" /></div>
            <div><label class="block text-sm font-bold text-gray-700 mb-1">Author</label><input v-model="editingResearch.author" type="text" class="w-full border p-2 rounded focus:ring-2 focus:ring-yellow-500 outline-none" /></div>
            <div><label class="block text-sm font-bold text-gray-700 mb-1">Abstract</label><textarea v-model="editingResearch.abstract" rows="4" class="w-full border p-2 rounded focus:ring-2 focus:ring-yellow-500 outline-none"></textarea></div>
            <div>
              <label class="block text-sm font-bold text-gray-700 mb-1">Replace PDF (Optional)</label>
              <input type="file" accept="application/pdf" @change="handleEditFileUpload" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-yellow-100 file:text-yellow-700 hover:file:bg-yellow-200 cursor-pointer" />
              <p class="text-xs text-gray-400 mt-1">Leave empty to keep existing file.</p>
            </div>
          </div>
          <div class="bg-gray-50 p-4 border-t flex justify-end gap-2">
            <button @click="editingResearch = null" class="px-4 py-2 text-gray-600 font-bold hover:bg-gray-200 rounded transition">Cancel</button>
            <button @click="saveChanges" :disabled="isSaving" class="px-6 py-2 bg-yellow-500 text-green-900 font-bold rounded hover:bg-yellow-600 transition flex items-center gap-2">
              <span v-if="isSaving" class="animate-spin h-4 w-4 border-2 border-green-900 border-t-transparent rounded-full"></span>{{ isSaving ? 'Saving...' : 'Save Changes' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>

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
            <button 
              @click="confirmModal.show = false" 
              class="px-5 py-2.5 rounded-xl font-bold text-gray-600 bg-gray-100 hover:bg-gray-200 transition"
            >
              Cancel
            </button>
            
            <button 
              @click="executeArchiveToggle" 
              :class="`px-5 py-2.5 rounded-xl font-bold text-white shadow-lg transform active:scale-95 transition ${confirmModal.action === 'Archive' ? 'bg-red-500 hover:bg-red-600 shadow-red-200' : 'bg-green-600 hover:bg-green-700 shadow-green-200'}`"
            >
              Yes, {{ confirmModal.action }}
            </button>
          </div>

        </div>

      </div>
    </Transition>
    <div v-if="commentModal.show" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg w-full max-w-lg p-6 flex flex-col max-h-[80vh]">
        
        <div class="flex justify-between items-center mb-4 border-b pb-2">
          <h3 class="text-lg font-bold">Revisions: {{ commentModal.title }}</h3>
          <button @click="commentModal.show = false" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
        </div>

        <div 
          ref="chatContainer" 
          class="flex-1 overflow-y-auto bg-gray-50 p-4 rounded mb-4 border border-gray-200 space-y-3"
        >
          <div 
            v-for="msg in commentModal.list" 
            :key="msg.id" 
            :class="['p-3 rounded-lg text-sm max-w-[85%]', msg.role === 'admin' ? 'bg-red-100 ml-auto border-l-4 border-red-500' : 'bg-green-100 border-l-4 border-green-500']"
          >
            <div class="font-bold text-xs mb-1">{{ msg.user_name }} ({{ msg.role }})</div>
            <p>{{ msg.comment }}</p>
            <div class="text-[10px] text-gray-500 text-right mt-1">{{ new Date(msg.created_at).toLocaleString() }}</div>
          </div>
          
          <p v-if="commentModal.list.length === 0" class="text-center text-gray-400 italic mt-10">No comments yet.</p>
        </div>

        <div class="flex gap-2">
          <textarea 
            v-model="commentModal.newComment" 
            placeholder="Reply to admin..." 
            class="flex-1 border rounded p-2 text-sm focus:ring-2 focus:ring-green-500 outline-none"
            rows="2"
          ></textarea>
          <button 
            @click="postComment" 
            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"
          >
            Send
          </button>
        </div>

      </div>
    </div>
  </div>
</template>

<style scoped>
/* Standard Fade */
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

/* Pop Animation for the Confirm Modal */
.pop-enter-active {
  animation: pop-in 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.pop-leave-active {
  transition: opacity 0.2s ease;
}
.pop-leave-to {
  opacity: 0;
}

@keyframes pop-in {
  0% { transform: scale(0.8); opacity: 0; }
  100% { transform: scale(1); opacity: 1; }
}

/* Wiggle Animation for Trash */
@keyframes wiggle {
  0%, 100% { transform: rotate(0deg); }
  25% { transform: rotate(-10deg); }
  75% { transform: rotate(10deg); }
}
.animate-wiggle {
  animation: wiggle 1s ease-in-out infinite;
}

/* Slow Spin for Restore */
.animate-spin-slow {
  animation: spin 3s linear infinite;
}
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>