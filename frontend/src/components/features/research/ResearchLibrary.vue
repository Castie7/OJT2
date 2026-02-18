<script setup lang="ts">
import { ref, computed } from 'vue' 
import { useResearchLibrary } from '../../../composables/useResearchLibrary'
import type { User } from '../../../types'
import { useToast } from '../../../composables/useToast'
import BaseButton from '../../ui/BaseButton.vue'
import BaseCard from '../../ui/BaseCard.vue'
import BaseInput from '../../ui/BaseInput.vue'
import BaseSelect from '../../ui/BaseSelect.vue'

// ✅ USE THE DYNAMIC URL
import { getAssetUrl } from '../../../services/api'
const ASSET_URL = getAssetUrl()

const props = defineProps<{
  currentUser: User | null
}>()

const emit = defineEmits<{
  (e: 'update-stats', count: number): void
}>()

const {
  searchQuery, selectedType, startDate, endDate, showArchived, viewMode, selectedResearch,
  isLoading, confirmModal, currentPage, 
  filteredResearches, paginatedResearches, totalPages,
  nextPage, prevPage, requestArchiveToggle, executeArchiveToggle, clearFilters
} = useResearchLibrary(props.currentUser, emit)

const { showToast } = useToast()

// Check if any filters are active
const hasActiveFilters = computed(() => {
  return searchQuery.value !== '' || 
         selectedType.value !== '' || 
         startDate.value !== '' || 
         endDate.value !== ''
})



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

// Map crop variation to a local background image (reused from HomeView logic)
const getCropImage = (crop?: string): string => {
  const c = (crop || '').toLowerCase()
  if (c.includes('sweetpotato') || c.includes('sweet potato') || c.includes('kamote')) {
    return '/images/crops/sweetpotato.jpg'
  }
  if (c.includes('potato')) {
    return '/images/crops/potato.jpg'
  }
  if (c.includes('cassava') || c.includes('kamoteng kahoy')) {
    return '/images/crops/cassava.jpg'
  }
  if (c.includes('yam') || c.includes('ubi')) {
    return '/images/crops/yam.jpg'
  }
  if (c.includes('taro') || c.includes('gabi')) {
    return '/images/crops/taro.jpg'
  }
  return '/images/crops/default.jpg'
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

const typeOptions = [
  { label: 'All Types', value: '' },
  { label: 'Research Paper', value: 'Research Paper' },
  { label: 'Book', value: 'Book' },
  { label: 'Journal', value: 'Journal' },
  { label: 'IEC Material', value: 'IEC Material' },
  { label: 'Thesis', value: 'Thesis' }
]

// Fix for vue-tsc unused variable error
void confirmModal
</script>

<template>
  <div class="animate-fade-in space-y-6">

    <div class="flex items-center justify-between">
         <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
            <span class="text-3xl">📚</span> Research Library
            <span class="bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded-full border border-gray-200">{{ filteredResearches.length }} items</span>
         </h1>
         <div class="flex items-center gap-2">
            <BaseButton 
                v-if="currentUser && currentUser.role === 'admin'" 
                @click="showArchived = !showArchived" 
                :variant="showArchived ? 'danger' : 'secondary'"
                size="sm"
            >
                {{ showArchived ? 'Exit Archive' : 'View Archive' }}
            </BaseButton>
         </div>
    </div>
    
    <div class="flex flex-col lg:flex-row gap-8 items-start">
        
        <!-- SIDEBAR FILTERS -->
        <div class="w-full lg:w-64 shrink-0 space-y-6">
            <BaseCard class="!p-5 space-y-4 sticky top-24">
                <div>
                     <label class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1 block">Search</label>
                     <BaseInput 
                        v-model="searchQuery" 
                        placeholder="Title, author, keywords..." 
                        label="" 
                        class="w-full"
                    />
                </div>

                <div>
                     <label class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1 block">Type</label>
                     <BaseSelect 
                        v-model="selectedType" 
                        :options="typeOptions" 
                        placeholder="All Types"
                        class="w-full"
                        label=""
                    />
                </div>

                <div>
                    <label class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1 block">Date Range</label>
                    <div class="space-y-2">
                        <BaseInput v-model="startDate" type="date" class="w-full" />
                        <BaseInput v-model="endDate" type="date" class="w-full" />
                    </div>
                </div>

                <div v-if="hasActiveFilters" class="pt-4 border-t border-gray-100">
                    <button @click="clearFilters" class="w-full text-sm text-red-600 hover:text-red-700 font-medium flex items-center justify-center gap-1">
                        <span>✕</span> Clear Filters
                    </button>
                </div>
            </BaseCard>
        </div>

        <!-- MAIN CONTENT -->
        <div class="flex-1 w-full min-w-0">
             
             <!-- Toolbar -->
             <div class="flex justify-between items-center mb-4">
                 <div class="flex gap-2 bg-gray-100 p-1 rounded-lg">
                    <button @click="viewMode = 'grid'" :class="['px-3 py-1.5 text-sm font-medium rounded-md transition', viewMode === 'grid' ? 'bg-white text-emerald-700 shadow-sm' : 'text-gray-500 hover:text-gray-700']">Grid</button>
                    <button @click="viewMode = 'list'" :class="['px-3 py-1.5 text-sm font-medium rounded-md transition', viewMode === 'list' ? 'bg-white text-emerald-700 shadow-sm' : 'text-gray-500 hover:text-gray-700']">List</button>
                 </div>
                 
                 <!-- Pagination Top -->
                 <div class="flex items-center gap-2 text-sm text-gray-500">
                    <span>Page {{ currentPage }} of {{ totalPages || 1 }}</span>
                    <div class="flex gap-1">
                        <button @click="prevPage" :disabled="currentPage === 1" class="p-1 hover:bg-gray-100 rounded disabled:opacity-30">◀</button>
                        <button @click="nextPage" :disabled="currentPage === totalPages || totalPages === 0" class="p-1 hover:bg-gray-100 rounded disabled:opacity-30">▶</button>
                    </div>
                 </div>
             </div>

             <Transition name="fade" mode="out-in">
                <!-- GRID VIEW -->
                <div v-if="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                     <div 
                        v-for="item in paginatedResearches" 
                        :key="item.id"
                        @click="selectedResearch = item"
                        class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-lg hover:-translate-y-1 transition-all duration-300 cursor-pointer group flex flex-col h-full"
                    >
                        <!-- Cover Image Area -->
                        <div class="h-32 bg-gray-100 relative overflow-hidden">
                             <img :src="getCropImage(item.crop_variation)" class="w-full h-full object-cover opacity-90 group-hover:scale-105 transition-transform duration-500" alt="Cover">
                             <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
                             
                             <div class="absolute bottom-3 left-3 right-3 flex justify-between items-end">
                                 <span class="bg-white/90 backdrop-blur-sm text-emerald-800 text-[10px] font-bold px-2 py-0.5 rounded shadow-sm">
                                    {{ item.knowledge_type }}
                                 </span>
                                 <span v-if="item.file_path" class="text-white bg-black/30 p-1 rounded-full text-xs">📎</span>
                             </div>
                        </div>

                        <!-- Content -->
                        <div class="p-5 flex-1 flex flex-col">
                            <h3 class="font-bold text-gray-900 leading-tight mb-2 line-clamp-2 group-hover:text-emerald-700 transition-colors" :title="item.title">{{ item.title }}</h3>
                            <p class="text-xs text-gray-500 mb-3">by {{ item.author }}</p>
                            
                            <div class="mt-auto pt-3 border-t border-gray-50 flex items-center justify-between text-xs text-gray-400">
                                <span>{{ formatDate(item.publication_date) }}</span>
                                <span v-if="item.crop_variation" class="text-emerald-600 font-medium">{{ item.crop_variation }}</span>
                            </div>
                        </div>
                     </div>
                </div>

                <!-- LIST VIEW -->
                <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
                   <table class="min-w-full divide-y divide-gray-100">
                      <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Research</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Type / Date</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Location</th>
                            <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Action</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-gray-100">
                        <tr v-for="item in paginatedResearches" :key="item.id" class="hover:bg-emerald-50/50 cursor-pointer transition-colors" @click="selectedResearch = item">
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-900 text-sm line-clamp-1">{{ item.title }}</div>
                                <div class="text-xs text-gray-500">{{ item.author }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded textxs font-medium bg-blue-50 text-blue-700 mb-1">
                                    {{ item.knowledge_type }}
                                </span>
                                <div class="text-xs text-gray-400">{{ formatDate(item.publication_date) }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded text-xs font-mono font-bold">{{ item.shelf_location || 'N/A' }}</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button v-if="currentUser && currentUser.role === 'admin'" @click.stop="requestArchiveToggle(item)" class="text-xs text-red-500 hover:text-red-700 font-medium px-2 py-1 hover:bg-red-50 rounded">Archive</button>
                            </td>
                        </tr>
                      </tbody>
                   </table>
                </div>
             </Transition>

            <!-- Empty State -->
            <div v-if="paginatedResearches.length === 0 && !isLoading" class="text-center py-20 bg-gray-50 rounded-xl border border-dashed border-gray-200 mt-4">
                <div class="text-5xl mb-4 opacity-20">🔍</div>
                <h3 class="text-lg font-bold text-gray-900">No Researches Found</h3>
                <p class="text-gray-500 text-sm">Try adjusting your filters or search terms.</p>
                <button @click="clearFilters" class="mt-4 text-emerald-600 font-bold text-sm hover:underline">Clear Filters</button>
            </div>
        </div>
    </div>

    <!-- Modal: View Details / PDF -->
    <Transition name="fade">
      <div v-if="selectedResearch" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/80 backdrop-blur-sm overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col overflow-hidden animate-pop" @click.stop>
          
          <div class="relative h-48 md:h-64 bg-gray-900 shrink-0">
             <img :src="getCropImage(selectedResearch.crop_variation)" class="w-full h-full object-cover opacity-40">
             <div class="absolute inset-0 bg-gradient-to-t from-gray-900 to-transparent"></div>
             
             <div class="absolute bottom-0 left-0 p-6 md:p-8 w-full">
                <div class="flex gap-2 mb-3">
                    <span class="bg-emerald-500 text-white text-[10px] uppercase font-bold px-2 py-1 rounded shadow-sm">{{ selectedResearch.knowledge_type }}</span>
                    <span v-if="selectedResearch.crop_variation" class="bg-white/20 text-white backdrop-blur-md text-[10px] uppercase font-bold px-2 py-1 rounded border border-white/20">{{ selectedResearch.crop_variation }}</span>
                </div>
                <h2 class="text-2xl md:text-4xl font-bold text-white leading-tight dropshadow-md">{{ selectedResearch.title }}</h2>
                <p class="text-emerald-200 text-sm md:text-base mt-2 font-medium">By {{ selectedResearch.author }}</p>
             </div>

             <button @click="selectedResearch = null" class="absolute top-4 right-4 bg-black/20 hover:bg-black/40 text-white rounded-full p-2 backdrop-blur-sm transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
             </button>
          </div>

          <div class="flex-1 overflow-y-auto p-6 bg-gray-50 custom-scrollbar">
              
             <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <!-- Metadata Side -->
                <div class="md:col-span-1 space-y-4">
                    <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                         <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4 border-b border-gray-100 pb-2">Catalog Info</h3>
                         <dl class="space-y-3 text-sm">
                            <div class="flex flex-col">
                                <dt class="text-gray-400 text-xs">Publisher</dt>
                                <dd class="font-medium text-gray-900">{{ selectedResearch.publisher || 'N/A' }}</dd>
                            </div>
                            <div class="flex flex-col">
                                <dt class="text-gray-400 text-xs">Date</dt>
                                <dd class="font-medium text-gray-900">{{ formatDate(selectedResearch.publication_date) }}</dd>
                            </div>
                            <div class="flex flex-col">
                                <dt class="text-gray-400 text-xs">ISBN/ISSN</dt>
                                <dd class="font-mono text-gray-600">{{ selectedResearch.isbn_issn || 'N/A' }}</dd>
                            </div>
                            <div class="flex flex-col">
                                <dt class="text-gray-400 text-xs">Shelf Location</dt>
                                <dd class="font-mono font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded w-fit">{{ selectedResearch.shelf_location || 'Unknown' }}</dd>
                            </div>
                            <div class="flex flex-col">
                                <dt class="text-gray-400 text-xs">Condition</dt>
                                <dd :class="`font-bold ${selectedResearch.item_condition === 'Good' ? 'text-green-600' : 'text-red-500'}`">{{ selectedResearch.item_condition }}</dd>
                            </div>
                         </dl>
                    </div>
                </div>

                <!-- Abstract / Access -->
                <div class="md:col-span-2 space-y-6">
                    <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
                        <h3 class="font-bold text-gray-800 mb-2">Description / Abstract</h3>
                        <p class="text-gray-600 text-sm leading-relaxed">{{ selectedResearch.physical_description || 'No description provided.' }}</p>
                    </div>

                    <div v-if="selectedResearch.file_path || selectedResearch.link" class="bg-blue-50/50 p-6 rounded-xl border border-blue-100">
                        <div class="flex justify-between items-center mb-4">
                             <h3 class="font-bold text-blue-900 flex items-center gap-2"><span>🌐</span> Digital Access</h3>
                             <button v-if="selectedResearch.file_path" @click="toggleFullscreen" class="text-xs font-bold text-blue-600 hover:text-blue-800 bg-white border border-blue-200 px-3 py-1 rounded shadow-sm">
                                Full Screen
                             </button>
                        </div>
                        
                        <div v-if="selectedResearch.file_path" ref="pdfContainer" class="w-full bg-gray-800 rounded-lg overflow-hidden shadow-lg h-[500px] border border-gray-200">
                             <iframe 
                                :src="`${ASSET_URL}/uploads/${selectedResearch.file_path}`" 
                                class="w-full h-full border-none bg-white" 
                                title="PDF Preview">
                             </iframe>
                        </div>

                        <div v-if="selectedResearch.link" class="mt-4">
                           <a :href="selectedResearch.link" target="_blank" class="flex items-center justify-center gap-2 w-full bg-blue-600 text-white font-bold py-3 rounded-lg hover:bg-blue-700 shadow-md hover:shadow-lg transition">
                              <span>🔗 Open External Link</span>
                           </a>
                        </div>
                    </div>
                </div>
             </div>

          </div>
        </div>
      </div>
    </Transition>

    <!-- Modal: Confirmation -->
    <Transition name="fade">
      <div v-if="confirmModal.show" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-gray-900/80 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden transform transition-all p-8 text-center animate-pop">
          <div class="mb-6 flex justify-center">
            <div class="w-20 h-20 rounded-full bg-gray-50 flex items-center justify-center text-4xl shadow-inner">
                {{ confirmModal.action === 'Archive' ? '🗑️' : '♻️' }}
            </div>
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-2">{{ confirmModal.title }}</h3>
          <p class="text-gray-500 text-sm mb-8 leading-relaxed">{{ confirmModal.subtext }}</p>
          <div class="flex gap-3 justify-center">
            <BaseButton @click="confirmModal.show = false" variant="ghost" class="w-full">Cancel</BaseButton>
            <BaseButton 
                @click="executeArchiveToggle" 
                :disabled="confirmModal.isProcessing" 
                :variant="confirmModal.action === 'Archive' ? 'danger' : 'primary'"
                class="w-full"
            >
                Yes, {{ confirmModal.action }}
            </BaseButton>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.animate-fade-in {
  animation: fadeIn 0.3s ease-out;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(5px); }
  to { opacity: 1; transform: translateY(0); }
}

.animate-pop {
    animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
@keyframes popIn {
    from { opacity: 0; transform: scale(0.95) translateY(10px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

/* Transitions */
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
</style>