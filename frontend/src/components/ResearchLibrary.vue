<script setup>
import { ref, onMounted, watch, computed } from 'vue'

const props = defineProps(['currentUser'])
const emit = defineEmits(['update-stats']) 

// Data States
const researches = ref([]) 
const searchQuery = ref('')
const showArchived = ref(false)
const viewMode = ref('list')
const selectedResearch = ref(null)

// UI States
const isLoading = ref(false)
const toast = ref({ show: false, message: '', type: 'success' }) 
const confirmModal = ref({ show: false, id: null, action: '', title: '', subtext: '' })

// --- PAGINATION STATE ---
const currentPage = ref(1)
const itemsPerPage = 10

// --- HELPER: SHOW TOAST ---
const showToast = (message, type = 'success') => {
  toast.value = { show: true, message, type }
  setTimeout(() => { toast.value.show = false }, 3000)
}

const getCookie = (name) => {
  const value = `; ${document.cookie}`;
  const parts = value.split(`; ${name}=`);
  if (parts.length === 2) return parts.pop().split(';').shift();
  return null;
}

// DATE FORMATTER
const formatSimpleDate = (dateStr) => {
  if (!dateStr) return 'N/A';
  return new Date(dateStr).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

// --- FETCH DATA ---
const fetchResearches = async () => {
  isLoading.value = true
  // Reset list slightly to indicate loading state if desired, or keep old data
  // researches.value = [] 
  try {
    const endpoint = showArchived.value 
      ? 'http://localhost:8080/research/archived' 
      : 'http://localhost:8080/research'

    const token = getCookie('auth_token');
    // Always attach headers if token exists (Admin needs it for Archive, Public doesn't hurt)
    const headers = token ? { 'Authorization': token } : {};

    const response = await fetch(endpoint, { headers })
    
    if (response.ok) {
      const data = await response.json()
      researches.value = data
      
      if (!showArchived.value) {
        emit('update-stats', data.length)
      }
    } else {
       if(showArchived.value) showToast("Access Denied to Archives", "error");
    }
  } catch (error) {
    showToast("Failed to load data.", "error")
  } finally {
    isLoading.value = false
  }
}

// --- FILTER & PAGINATION LOGIC ---
const filteredResearches = computed(() => {
  if (!searchQuery.value) return researches.value
  const query = searchQuery.value.toLowerCase()
  return researches.value.filter(item => 
    item.title.toLowerCase().includes(query) || 
    item.author.toLowerCase().includes(query)
  )
})

const paginatedResearches = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage
  const end = start + itemsPerPage
  return filteredResearches.value.slice(start, end)
})

const totalPages = computed(() => {
  return Math.ceil(filteredResearches.value.length / itemsPerPage)
})

// --- WATCHER FIX HERE ---
watch([searchQuery, showArchived], () => {
  currentPage.value = 1
  fetchResearches() // <--- UPDATED: Always fetch, regardless of tab
})

// 5. Navigation Functions
const nextPage = () => {
  if (currentPage.value < totalPages.value) currentPage.value++
}
const prevPage = () => {
  if (currentPage.value > 1) currentPage.value--
}

onMounted(() => {
  fetchResearches()
})

// --- ARCHIVE LOGIC ---
const requestArchiveToggle = (item) => {
  const action = showArchived.value ? 'Restore' : 'Archive';
  confirmModal.value = {
    show: true, id: item.id, action: action,
    title: action === 'Archive' ? 'Move to Trash?' : 'Restore Research?',
    subtext: action === 'Archive' ? `Remove "${item.title}"?` : `Restore "${item.title}"?`
  }
}

const executeArchiveToggle = async () => {
  if (!confirmModal.value.id) return;
  
  const token = getCookie('auth_token');
  if(!token) { showToast("Authentication Error", "error"); return; }

  try {
    // UPDATED: Dynamic Endpoint logic
    const endpoint = confirmModal.value.action === 'Restore'
      ? `http://localhost:8080/research/restore/${confirmModal.value.id}`
      : `http://localhost:8080/research/archive/${confirmModal.value.id}`;

    const response = await fetch(endpoint, { 
      method: 'POST',
      headers: { 'Authorization': token } 
    })
    
    if(response.ok) {
        fetchResearches() 
        showToast(`Item ${confirmModal.value.action}d successfully!`, "success")
        confirmModal.value.show = false
    } else {
        const err = await response.json();
        showToast("Failed: " + (err.message || "Access Denied"), "error");
    }
  } catch (error) { showToast("Error updating status", "error") }
}
</script>

<template>
  <div>
    <Transition name="slide-fade">
      <div v-if="toast.show" :class="`fixed bottom-5 right-5 z-[100] px-6 py-4 rounded-lg shadow-2xl flex items-center gap-3 text-white font-bold transition-all ${toast.type === 'error' ? 'bg-red-600' : 'bg-green-600'}`">
        <span>{{ toast.type === 'error' ? '⚠️' : '✅' }}</span><span>{{ toast.message }}</span>
      </div>
    </Transition>

    <div class="flex justify-between items-center mb-6">
       <div class="flex items-center gap-4">
         <img src="/logo.png" alt="BSU Logo" class="h-12 w-auto object-contain hover:scale-105 transition-transform duration-300" />
         <h1 class="text-3xl font-bold text-gray-900">Research Library</h1>
       </div>
    </div>
    
    <div class="w-full">
      <div class="bg-white p-6 rounded-lg shadow-lg min-h-[500px] relative flex flex-col">
        
        <div class="flex flex-col xl:flex-row justify-between items-center mb-6 border-b pb-4 gap-4">
          <h2 class="text-xl font-bold text-gray-800 whitespace-nowrap">
            {{ showArchived ? '🗑️ Archived Researches' : '📚 Available Studies' }}
            <span class="text-sm font-normal text-gray-500 ml-2">({{ filteredResearches.length }} items)</span>
          </h2>
          <div class="flex flex-col sm:flex-row gap-3 w-full xl:w-auto">
            <div class="relative w-full sm:w-64">
              <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">🔍</span>
              <input v-model="searchQuery" type="text" placeholder="Search title or author..." class="w-full pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 text-sm"/>
            </div>
            <div class="flex gap-2">
              <button 
                v-if="currentUser && currentUser.role === 'admin'" 
                @click="showArchived = !showArchived" 
                :class="`px-4 py-2 text-sm font-bold rounded-md border transition whitespace-nowrap ${showArchived ? 'bg-red-100 text-red-700 border-red-300' : 'bg-gray-100 text-gray-600 border-gray-200 hover:bg-gray-200'}`"
              >
                {{ showArchived ? 'View Active' : 'View Archive' }}
              </button>
              <div class="flex bg-gray-100 p-1 rounded-lg shrink-0">
                <button @click="viewMode = 'list'" :class="`px-3 py-1 text-sm font-medium rounded-md transition ${viewMode === 'list' ? 'bg-white text-green-700 shadow' : 'text-gray-500 hover:text-gray-700'}`">📃</button>
                <button @click="viewMode = 'grid'" :class="`px-3 py-1 text-sm font-medium rounded-md transition ${viewMode === 'grid' ? 'bg-white text-green-700 shadow' : 'text-gray-500 hover:text-gray-700'}`">🔲</button>
              </div>
            </div>
          </div>
        </div>

        <div v-if="isLoading" class="flex flex-col items-center justify-center py-20 text-gray-400">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-green-600 mb-3"></div>
          <p>Loading researches...</p>
        </div>

        <Transition name="fade" mode="out-in">
          <div v-if="!isLoading" class="flex-1 flex flex-col">
            
            <div v-if="viewMode === 'list'" class="overflow-x-auto flex-1">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Title</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Author</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Actions</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="item in paginatedResearches" :key="item.id" class="hover:bg-green-50 transition">
                    <td @click="selectedResearch = item" class="px-6 py-4 font-medium text-gray-900 cursor-pointer">{{ item.title }}</td>
                    <td @click="selectedResearch = item" class="px-6 py-4 text-gray-500 cursor-pointer">{{ item.author }}</td>
                    <td class="px-6 py-4 flex items-center gap-2">
                       <button @click="selectedResearch = item" class="text-xs px-2 py-1 rounded font-bold border text-blue-600 border-blue-200 hover:bg-blue-50">View PDF</button>
                       <button 
                         v-if="currentUser && currentUser.role === 'admin'"
                         @click.stop="requestArchiveToggle(item)" 
                         :class="`text-xs px-2 py-1 rounded font-bold border ${showArchived ? 'text-green-600 border-green-200 hover:bg-green-100' : 'text-red-600 border-red-200 hover:bg-red-100'}`"
                       >
                        {{ showArchived ? 'Restore' : 'Archive' }}
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 flex-1">
              <div v-for="item in paginatedResearches" :key="item.id" class="group bg-gray-50 hover:bg-white border border-gray-200 hover:border-green-400 rounded-xl p-5 transition shadow hover:shadow-lg flex flex-col relative">
                 <button 
                  v-if="currentUser && currentUser.role === 'admin'"
                  @click.stop="requestArchiveToggle(item)" 
                  :class="`absolute top-2 right-2 text-xs px-2 py-1 rounded font-bold border z-10 ${showArchived ? 'bg-green-100 text-green-700' : 'bg-red-50 text-red-600 hover:bg-red-100'}`"
                 >
                  {{ showArchived ? 'Restore' : 'Archive' }}
                </button>
                <div @click="selectedResearch = item" class="cursor-pointer h-full flex flex-col">
                  <div class="h-32 bg-gray-200 rounded-lg mb-4 flex items-center justify-center text-gray-400 group-hover:bg-green-50 group-hover:text-green-600 transition"><span class="text-4xl">📄</span></div>
                  <h3 class="font-bold text-gray-900 text-lg leading-tight mb-1 group-hover:text-green-700">{{ item.title }}</h3>
                  <p class="text-sm text-gray-500 mb-2">By {{ item.author }}</p>
                  <p class="text-sm text-gray-400 line-clamp-2">{{ item.abstract }}</p>
                  <div class="mt-auto pt-4 text-blue-600 text-xs font-bold hover:underline">Read PDF →</div>
                </div>
              </div>
            </div>

            <div v-if="filteredResearches.length === 0" class="text-center py-12 text-gray-500">
              <span v-if="searchQuery">No results found for "{{ searchQuery }}".</span>
              <span v-else>{{ showArchived ? 'Archive is empty.' : 'No active researches found.' }}</span>
            </div>

            <div v-if="filteredResearches.length > itemsPerPage" class="mt-6 flex justify-between items-center border-t pt-4">
              <span class="text-sm text-gray-500">
                Showing {{ ((currentPage - 1) * itemsPerPage) + 1 }} to {{ Math.min(currentPage * itemsPerPage, filteredResearches.length) }} of {{ filteredResearches.length }} entries
              </span>
              
              <div class="flex gap-2">
                <button 
                  @click="prevPage" 
                  :disabled="currentPage === 1"
                  class="px-4 py-2 text-sm font-bold rounded-lg border bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition"
                >
                  Previous
                </button>
                
                <span class="px-4 py-2 text-sm font-bold bg-green-50 text-green-700 rounded-lg border border-green-200">
                  Page {{ currentPage }} of {{ totalPages }}
                </span>

                <button 
                  @click="nextPage" 
                  :disabled="currentPage === totalPages"
                  class="px-4 py-2 text-sm font-bold rounded-lg border bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition"
                >
                  Next
                </button>
              </div>
            </div>

          </div>
        </Transition>
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
             <div v-if="selectedResearch.file_path" class="bg-white p-1 rounded shadow h-[600px]"><iframe :src="`http://localhost:8080/uploads/${selectedResearch.file_path}`" class="w-full h-full border-none rounded" title="PDF Viewer"></iframe></div>
          </div>
        </div>
      </div>
    </Transition>

    <Transition name="pop">
      <div v-if="confirmModal.show" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black bg-opacity-60 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden transform transition-all p-6 text-center">
          <div class="mb-4 flex justify-center">
            <div v-if="confirmModal.action === 'Archive'" class="text-6xl animate-wiggle">🗑️</div>
            <div v-else class="text-6xl animate-spin-slow">♻️</div>
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-2">{{ confirmModal.title }}</h3>
          <p class="text-gray-500 text-sm mb-6">{{ confirmModal.subtext }}</p>
          <div class="flex gap-3 justify-center">
            <button @click="confirmModal.show = false" class="px-5 py-2.5 rounded-xl font-bold text-gray-600 bg-gray-100 hover:bg-gray-200 transition">Cancel</button>
            <button @click="executeArchiveToggle" :class="`px-5 py-2.5 rounded-xl font-bold text-white shadow-lg transform active:scale-95 transition ${confirmModal.action === 'Archive' ? 'bg-red-500 hover:bg-red-600 shadow-red-200' : 'bg-green-600 hover:bg-green-700 shadow-green-200'}`">Yes, {{ confirmModal.action }}</button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
/* Animations */
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.slide-fade-enter-active { transition: all 0.3s ease-out; }
.slide-fade-leave-active { transition: all 0.4s cubic-bezier(1, 0.5, 0.8, 1); }
.slide-fade-enter-from, .slide-fade-leave-to { transform: translateX(20px); opacity: 0; }
.pop-enter-active { animation: pop-in 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
.pop-leave-active { transition: opacity 0.2s ease; }
.pop-leave-to { opacity: 0; }
@keyframes pop-in { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
@keyframes wiggle { 0%, 100% { transform: rotate(0deg); } 25% { transform: rotate(-10deg); } 75% { transform: rotate(10deg); } }
.animate-wiggle { animation: wiggle 1s ease-in-out infinite; }
.animate-spin-slow { animation: spin 3s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>