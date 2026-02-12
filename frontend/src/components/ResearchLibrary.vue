<script setup lang="ts">
import { ref } from 'vue' 
import { useResearchLibrary, type User } from '../composables/useResearchLibrary'
import { useToast } from '../composables/useToast'

// ✅ USE THE DYNAMIC URL
import { getAssetUrl } from '../services/api'
const ASSET_URL = getAssetUrl()

const props = defineProps<{
  currentUser: User | null
}>()

const emit = defineEmits<{
  (e: 'update-stats', count: number): void
}>()

const {
  searchQuery, selectedType, showArchived, viewMode, selectedResearch,
  isLoading, confirmModal, currentPage, 
  filteredResearches, paginatedResearches, totalPages,
  nextPage, prevPage, requestArchiveToggle, executeArchiveToggle
} = useResearchLibrary(props.currentUser, emit)

const { showToast } = useToast()

// Helper to handle both string dates and Backend-returned DateTime objects
const formatDate = (date: any) => {
  if (!date) return '-'
  
  let dateStr = date
  // If it's a DateTime object produced by PHP/CodeIgniter
  if (typeof date === 'object' && date.date) {
    dateStr = date.date
  }
  
  try {
    const d = new Date(dateStr)
    // Check if valid date
    if (isNaN(d.getTime())) return dateStr

    return d.toLocaleDateString('en-US', { 
      year: 'numeric', 
      month: 'short', 
      day: 'numeric' 
    })
  } catch (e) {
    return dateStr
  }
}

// --- Fullscreen Logic ---
const pdfContainer = ref<HTMLElement | null>(null)

const toggleFullscreen = () => {
  if (!pdfContainer.value) return

  if (!document.fullscreenElement) {
    pdfContainer.value.requestFullscreen().catch((err: any) => {
      showToast(`Error attempting to enable full-screen mode: ${err.message} (${err.name})`, 'error');
    });
  } else {
    document.exitFullscreen();
  }
}
</script>

<template>
  <div>
    <!-- Toast Removed (Global Toast used instead) -->

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
            {{ showArchived ? '🗑️ Archived Items' : '📚 Library Catalog' }}
            <span class="text-sm font-normal text-gray-500 ml-2">({{ filteredResearches.length }} found)</span>
          </h2>

          <div class="flex flex-col sm:flex-row gap-3 w-full xl:w-auto">
            <select v-model="selectedType" class="border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 outline-none bg-gray-50">
              <option value="">All Types</option>
              <option>Research Paper</option>
              <option>Book</option>
              <option>Journal</option>
              <option>IEC Material</option>
              <option>Thesis</option>
            </select>
            <div class="relative w-full sm:w-64">
              <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">🔍</span>
              <input v-model="searchQuery" type="text" placeholder="Search title, author, subject..." class="w-full pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 text-sm"/>
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

        <Transition name="fade" mode="out-in">
          <div v-if="!isLoading" class="flex-1 flex flex-col">
            
            <div v-if="viewMode === 'list'" class="overflow-x-auto flex-1">
              <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-3 text-left font-bold text-gray-500 uppercase">Title / Author</th>
                    <th class="px-4 py-3 text-left font-bold text-gray-500 uppercase">Category</th>
                    <th class="px-4 py-3 text-left font-bold text-gray-500 uppercase">Location / Cond.</th>
                    <th class="px-4 py-3 text-left font-bold text-gray-500 uppercase">Access</th>
                    <th class="px-4 py-3 text-right font-bold text-gray-500 uppercase">Actions</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="item in paginatedResearches" :key="item.id" class="hover:bg-green-50 transition">
                    <td class="px-4 py-4 cursor-pointer" @click="selectedResearch = item">
                      <div class="font-bold text-gray-900 hover:text-green-700">{{ item.title }}</div>
                      <div class="text-xs text-gray-500">{{ item.author }}</div>
                    </td>
                    <td class="px-4 py-4 cursor-pointer" @click="selectedResearch = item">
                        <span class="inline-block px-2 py-1 text-[10px] font-bold rounded bg-blue-50 text-blue-700 uppercase mb-1">
                          {{ item.knowledge_type }}
                        </span>
                        <div v-if="item.crop_variation" class="text-xs text-amber-600 italic">
                          {{ item.crop_variation }}
                        </div>
                    </td>
                    <td class="px-4 py-4 text-xs cursor-pointer" @click="selectedResearch = item">
                        <div class="font-mono text-gray-600 font-bold">{{ item.shelf_location || 'No Location' }}</div>
                        <span :class="`inline-block mt-1 px-1.5 rounded text-[10px] ${item.item_condition === 'Good' || item.item_condition === 'New' ? 'bg-green-100 text-green-700' : 'bg-red-50 text-red-600'}`">
                          {{ item.item_condition }}
                        </span>
                    </td>
                    <td class="px-4 py-4">
                        <div class="flex gap-2">
                          <button v-if="item.file_path" @click.stop="selectedResearch = item" class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-2 py-1 rounded border">
                            📄 PDF
                          </button>
                          <a v-if="item.link" :href="item.link" target="_blank" class="text-xs bg-blue-50 hover:bg-blue-100 text-blue-700 px-2 py-1 rounded border border-blue-200 flex items-center gap-1">
                            🔗 Link
                          </a>
                        </div>
                    </td>
                    <td class="px-4 py-4 text-right">
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
              <div v-for="item in paginatedResearches" :key="item.id" class="group bg-white border border-gray-200 hover:border-green-400 rounded-xl p-5 shadow-sm hover:shadow-md transition relative flex flex-col h-full cursor-pointer" @click="selectedResearch = item">
                 <div class="flex justify-between items-start mb-2">
                    <span class="bg-gray-100 text-gray-600 text-[10px] font-bold px-2 py-1 rounded uppercase tracking-wide">
                      {{ item.knowledge_type }}
                    </span>
                    <span v-if="item.file_path" class="text-xs">📎</span>
                 </div>
                 <h3 class="font-bold text-gray-900 text-lg leading-tight mb-1 group-hover:text-green-700 line-clamp-2">{{ item.title }}</h3>
                 <p class="text-sm text-gray-500 mb-3">By {{ item.author }}</p>
                 <div class="mt-auto pt-3 border-t text-xs text-gray-600 space-y-1">
                    <div class="flex justify-between"><span>Location:</span> <span class="font-mono font-bold">{{ item.shelf_location || 'N/A' }}</span></div>
                    <div v-if="item.crop_variation" class="flex justify-between text-amber-600"><span>Crop:</span> <span>{{ item.crop_variation }}</span></div>
                 </div>
              </div>
            </div>

            <div class="mt-6 flex flex-col sm:flex-row justify-between items-center border-t pt-4 gap-4">
              <span class="text-sm text-gray-500">
                  Page {{ currentPage }} of {{ totalPages || 1 }}
              </span>
              <div class="flex gap-2">
                <button @click="prevPage" :disabled="currentPage === 1" class="px-4 py-2 text-sm font-bold rounded border hover:bg-gray-50 disabled:opacity-50">Previous</button>
                <button @click="nextPage" :disabled="currentPage === totalPages || totalPages === 0" class="px-4 py-2 text-sm font-bold rounded border hover:bg-gray-50 disabled:opacity-50">Next</button>
              </div>
            </div>

          </div>
        </Transition>
      </div>
    </div>

    <Transition name="fade">
      <div v-if="selectedResearch" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-75 backdrop-blur-sm overflow-y-auto">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden" @click.stop>
          
          <div class="bg-green-800 text-white p-5 flex justify-between items-start shrink-0">
            <div>
              <span class="bg-green-900 text-green-100 text-[10px] uppercase font-bold px-2 py-1 rounded mb-2 inline-block">{{ selectedResearch.knowledge_type }}</span>
              <h2 class="text-2xl font-bold leading-tight">{{ selectedResearch.title }}</h2>
              <p class="text-green-200 text-sm mt-1">Author: {{ selectedResearch.author }}</p>
            </div>
            <button @click="selectedResearch = null" class="text-white hover:text-gray-300 text-3xl font-bold leading-none">&times;</button>
          </div>

          <div class="flex-1 overflow-y-auto p-6 bg-gray-50 custom-scrollbar">
              
             <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="bg-white p-5 rounded-lg border shadow-sm space-y-3">
                   <h3 class="font-bold text-gray-800 border-b pb-2 mb-2">📖 Catalog Details</h3>
                   <div class="grid grid-cols-3 gap-2 text-sm">
                      <span class="text-gray-500">Publisher:</span> <span class="col-span-2 font-medium">{{ selectedResearch.publisher || '-' }}</span>
                      <span class="text-gray-500">Edition:</span> <span class="col-span-2">{{ selectedResearch.edition || '-' }}</span>
                      <span class="text-gray-500">Date:</span> <span class="col-span-2">{{ formatDate(selectedResearch.publication_date) }}</span>
                      <span class="text-gray-500">ISBN/ISSN:</span> <span class="col-span-2 font-mono text-gray-600">{{ selectedResearch.isbn_issn || '-' }}</span>
                      <span class="text-gray-500">Description:</span> <span class="col-span-2">{{ selectedResearch.physical_description || '-' }}</span>
                   </div>
                </div>

                <div class="bg-white p-5 rounded-lg border shadow-sm space-y-3">
                   <h3 class="font-bold text-gray-800 border-b pb-2 mb-2">📍 Location & Topic</h3>
                   <div class="grid grid-cols-3 gap-2 text-sm">
                      <span class="text-gray-500">Shelf Loc:</span> <span class="col-span-2 font-mono font-bold text-green-700 text-lg">{{ selectedResearch.shelf_location || 'Unknown' }}</span>
                      <span class="text-gray-500">Condition:</span> <span class="col-span-2">{{ selectedResearch.item_condition }}</span>
                      <span class="text-gray-500">Crop:</span> <span class="col-span-2 text-amber-600 font-medium">{{ selectedResearch.crop_variation || 'General' }}</span>
                      <span class="text-gray-500">Subjects:</span> <span class="col-span-2 italic text-gray-600">{{ selectedResearch.subjects || 'No keywords' }}</span>
                   </div>
                </div>
             </div>

             <div v-if="selectedResearch.file_path || selectedResearch.link" class="bg-blue-50 p-4 rounded-lg border border-blue-100">
                <h3 class="font-bold text-blue-900 mb-3 flex items-center gap-2">🌐 Digital Access</h3>
                
                <div class="flex flex-wrap gap-4">
                  
                  <div v-if="selectedResearch.file_path" class="w-full">
                      <div class="flex justify-between items-center mb-2">
                         <p class="text-xs text-blue-600 font-bold uppercase">Attached Document:</p>
                         <button @click="toggleFullscreen" class="text-xs flex items-center gap-1 bg-white border border-blue-200 text-blue-600 px-2 py-1 rounded hover:bg-blue-50 font-bold transition">
                           ⛶ Full Screen
                         </button>
                      </div>
                      
                      <div ref="pdfContainer" class="w-full bg-black rounded overflow-hidden shadow-lg h-[500px]">
                          <iframe 
                             :src="`${ASSET_URL}/uploads/${selectedResearch.file_path}`" 
                             class="w-full h-full border-none bg-white" 
                             title="PDF Preview">
                          </iframe>
                      </div>
                   </div>

                   <div v-if="selectedResearch.link" class="w-full mt-2">
                      <a :href="selectedResearch.link" target="_blank" class="flex items-center justify-center gap-2 w-full bg-blue-600 text-white font-bold py-3 rounded-lg shadow hover:bg-blue-700 transition">
                         <span>🔗 Open External Link / Website</span>
                      </a>
                   </div>
                </div>
             </div>
             <div v-else class="text-center py-8 text-gray-400 italic bg-white rounded border border-dashed">
                No digital copy available for this item.
             </div>

          </div>
        </div>
      </div>
    </Transition>

    <Transition name="pop">
      <div v-if="confirmModal.show" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black bg-opacity-60 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden transform transition-all p-6 text-center">
          <div class="mb-4 flex justify-center">
            <div class="text-6xl">{{ confirmModal.action === 'Archive' ? '🗑️' : '♻️' }}</div>
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-2">{{ confirmModal.title }}</h3>
          <p class="text-gray-500 text-sm mb-6">{{ confirmModal.subtext }}</p>
          <div class="flex gap-3 justify-center">
            <button @click="confirmModal.show = false" class="px-5 py-2.5 rounded-xl font-bold text-gray-600 bg-gray-100 hover:bg-gray-200 transition">Cancel</button>
            <button @click="executeArchiveToggle" :class="`px-5 py-2.5 rounded-xl font-bold text-white shadow-lg ${confirmModal.action === 'Archive' ? 'bg-red-500 hover:bg-red-600' : 'bg-green-600 hover:bg-green-700'}`">Yes, {{ confirmModal.action }}</button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped src="../assets/styles/ResearchLibrary.css"></style>