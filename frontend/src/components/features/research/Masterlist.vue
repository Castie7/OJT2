<script setup lang="ts">
import { useMasterlist } from '../../../composables/useMasterlist'
import { getAssetUrl } from '../../../services/api'
const ASSET_URL = getAssetUrl()
import { ref, computed } from 'vue'
import BaseButton from '../../ui/BaseButton.vue'
import BaseCard from '../../ui/BaseCard.vue'
import BaseInput from '../../ui/BaseInput.vue'
import BaseSelect from '../../ui/BaseSelect.vue'

const pdfContainer = ref<HTMLElement | null>(null)
const toggleFullscreen = () => {
  if (!pdfContainer.value) return
  if (!document.fullscreenElement) {
    pdfContainer.value.requestFullscreen().catch(err => console.error(err))
  } else {
    document.exitFullscreen()
  }
}

const {
  isLoading, isRefreshing, searchQuery, statusFilter,
  currentPage, itemsPerPage, filteredItems, paginatedItems, totalPages,
  nextPage, prevPage,
  isEditModalOpen, isSaving, editForm,
  openEdit, handleFileChange, saveEdit,
  getStatusBadge, formatDate, resetFilters,
  confirmModal, requestArchive, executeArchive,
  selectedItem, viewDetails, closeDetails,
  approveResearch, rejectResearch
} = useMasterlist()

const hasActiveFilters = computed(() => {
  return searchQuery.value !== '' || statusFilter.value !== 'ALL'
})



const statusOptions = [
    { value: 'ALL', label: 'All Statuses' },
    { value: 'APPROVED', label: 'Published' },
    { value: 'PENDING', label: 'Pending' },
    { value: 'REJECTED', label: 'Rejected' },
    { value: 'ARCHIVED', label: 'Archived' },
]

const cropOptions = [
  'Sweet Potato', 'Potato', 'Yam Aeroponics', 'Yam Minisetts', 'Taro', 'Cassava', 
  'Yacon', 'Ginger', 'Canna', 'Arrowroot', 'Turmeric', 'Tannia', 'Kinampay', 
  'Zambal', 'Bengueta', 'Immitlog', 'Beniazuma', 'Haponita', 'Ganza', 'Montanosa', 
  'Igorota', 'Solibao', 'Raniag', 'Dalisay', 'Others'
].map(c => ({ value: c, label: c }))

const conditionOptions = ['New', 'Good', 'Fair', 'Poor', 'Damaged'].map(c => ({ value: c, label: c }))
</script>

<template>
  <div class="space-y-6 animate-fade-in">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <span>📋</span> Masterlist
        </h1>
        <p class="text-sm text-gray-500">View and edit all knowledge products across every status.</p>
      </div>
      <span class="text-sm font-medium bg-gray-100 px-3 py-1 rounded-full text-gray-600">
          {{ filteredItems.length }} entries found
      </span>
    </div>

    <!-- Filters Toolbar -->
    <BaseCard class="flex flex-wrap gap-4 items-center !p-4">
      <div class="relative flex-1 min-w-[240px]">
        <BaseInput 
            v-model="searchQuery" 
            placeholder="Search title, author, crop..." 
            class="w-full"
        />
      </div>

      <div class="w-48">
          <BaseSelect 
            v-model="statusFilter" 
            :options="statusOptions" 
            placeholder="Status"
          />
      </div>

      <button 
        @click="resetFilters"
        class="bg-gray-100 hover:bg-gray-200 text-gray-600 p-2.5 rounded-lg transition-colors shadow-sm"
        title="Refresh & Reset Filters"
        :disabled="isRefreshing"
      >
        <svg 
          xmlns="http://www.w3.org/2000/svg" 
          class="h-5 w-5 transition-transform" 
          :class="{ 'animate-spin-refresh': isRefreshing }"
          fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
        >
          <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
      </button>
    </BaseCard>

    <!-- Table -->
    <BaseCard class="overflow-hidden !p-0 min-h-[500px] flex flex-col">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100 table-fixed">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider w-1/4">Title</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider w-1/5">Author</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider w-1/6">Status</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider w-1/6">Type</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider w-1/6">Date</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider w-[10%]">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-50">
            <tr v-if="isLoading">
              <td colspan="6" class="px-6 py-20 text-center text-gray-400">
                <div class="flex flex-col items-center gap-2">
                  <div class="w-8 h-8 border-2 border-emerald-500 border-t-transparent rounded-full animate-spin"></div>
                  <span>Loading masterlist...</span>
                </div>
              </td>
            </tr>

            <tr v-else-if="filteredItems.length === 0">
              <td colspan="6" class="px-6 py-20 text-center">
                <div class="flex flex-col items-center justify-center max-w-md mx-auto opacity-60">
                  <div class="text-5xl mb-3">🔍</div>
                  <h3 class="text-lg font-bold text-gray-800 mb-2">No Results Found</h3>
                  <p class="text-gray-600 mb-4 text-sm">We couldn't find any entries matching your filters.</p>
                  
                  <button 
                    v-if="hasActiveFilters"
                    @click="resetFilters" 
                    class="px-5 py-2 bg-emerald-600 text-white font-bold rounded-lg hover:bg-emerald-700 transition shadow-md text-sm"
                  >
                    Clear All Filters
                  </button>
                </div>
              </td>
            </tr>

            <tr 
              v-else
              v-for="item in paginatedItems" :key="item.id" v-memo="[item.id, item.status, item.title, item.updated_at]"
              class="hover:bg-gray-50/80 transition cursor-pointer border-l-4 group"
              :class="{
                'border-l-emerald-500': item.status === 'approved',
                'border-l-yellow-400': item.status === 'pending',
                'border-l-red-500': item.status === 'rejected',
                'border-l-gray-400': item.status === 'archived',
                'border-l-transparent': !['approved','pending','rejected','archived'].includes(item.status)
              }"
              @click="viewDetails(item)"
            >
              <td class="px-6 py-4">
                <div class="font-bold text-gray-900 line-clamp-2 max-w-[280px] group-hover:text-emerald-700 transition-colors" :title="item.title">{{ item.title }}</div>
                <div v-if="item.crop_variation" class="text-xs text-emerald-600 font-medium mt-0.5">{{ item.crop_variation }}</div>
              </td>
              <td class="px-6 py-4 text-sm text-gray-700 font-medium">{{ item.author }}</td>
              <td class="px-6 py-4">
                <span :class="['px-2 py-1 text-xs font-bold rounded-full border shadow-sm', getStatusBadge(item.status).classes]">
                  {{ getStatusBadge(item.status).label }}
                </span>
              </td>
              <td class="px-6 py-4 text-sm text-gray-500">{{ item.knowledge_type || '—' }}</td>
              <td class="px-6 py-4 text-sm text-gray-500">{{ formatDate(item.created_at) }}</td>
              <td class="px-6 py-4 text-right">
                <div class="flex items-center justify-end gap-2" @click.stop>
                  <button 
                    v-if="item.file_path"
                    @click.stop="viewDetails(item)" 
                    class="p-2 rounded-full text-blue-500 hover:bg-blue-50 transition-colors"
                    title="View PDF"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                  </button>
                  <button 
                    @click.stop="openEdit(item)" 
                    class="p-2 rounded-full text-gray-400 hover:bg-emerald-50 hover:text-emerald-600 transition-colors"
                    title="Edit Details"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                    </svg>
                  </button>
                  <button 
                    @click.stop="requestArchive(item)"
                    :class="`p-2 rounded-full transition-colors ${
                      item.status === 'archived' 
                        ? 'text-emerald-500 hover:bg-emerald-50' 
                        : 'text-red-400 hover:bg-red-50 hover:text-red-600'
                    }`"
                    :title="item.status === 'archived' ? 'Restore Item' : 'Archive Item'"
                  >
                    <svg v-if="item.status === 'archived'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination in Footer -->
      <div v-if="filteredItems.length > itemsPerPage" class="mt-auto border-t bg-gray-50/50 p-4 flex justify-between items-center">
        <span class="text-xs text-gray-500 font-medium">
          Showing {{ ((currentPage - 1) * itemsPerPage) + 1 }} - {{ Math.min(currentPage * itemsPerPage, filteredItems.length) }} of {{ filteredItems.length }}
        </span>
        <div class="flex gap-2">
          <button 
            @click="prevPage" 
            :disabled="currentPage === 1"
            class="px-3 py-1 text-xs font-bold rounded border bg-white hover:bg-gray-50 disabled:opacity-50 transition shadow-sm"
          >
            Previous
          </button>
          <span class="px-3 py-1 text-xs font-bold bg-emerald-100 text-emerald-700 rounded border border-emerald-200">
            {{ currentPage }} / {{ totalPages }}
          </span>
          <button 
            @click="nextPage" 
            :disabled="currentPage >= totalPages"
            class="px-3 py-1 text-xs font-bold rounded border bg-white hover:bg-gray-50 disabled:opacity-50 transition shadow-sm"
          >
            Next
          </button>
        </div>
      </div>
    </BaseCard>

    <!-- Edit Modal -->
    <Transition name="fade">
      <div v-if="isEditModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm overflow-y-auto">
        <div class="bg-white rounded-2xl w-full max-w-4xl overflow-hidden shadow-2xl transform transition-all flex flex-col max-h-[90vh] animate-pop">
          
          <div class="bg-emerald-900 text-white p-4 flex justify-between items-center shrink-0">
            <h2 class="font-bold text-lg flex items-center gap-2"><span>✏️</span> Edit Knowledge Product</h2>
            <button @click="isEditModalOpen = false" class="text-white/70 hover:text-white transition w-8 h-8 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 font-bold">&times;</button>
          </div>

          <div class="p-6 overflow-y-auto custom-scrollbar flex-1 bg-gray-50">
            <form @submit.prevent="saveEdit" class="space-y-6">

              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                 <!-- Type -->
                 <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-3">Type <span class="text-red-500">*</span></label>
                    <div class="space-y-2">
                         <label v-for="type in ['Research Paper', 'Book', 'Journal', 'IEC Material', 'Thesis']" :key="type" class="flex items-center gap-3 p-2 rounded hover:bg-emerald-50 cursor-pointer transition">
                            <input type="checkbox" v-model="editForm.knowledge_type" :value="type" class="w-4 h-4 text-emerald-600 rounded border-gray-300 focus:ring-emerald-500">
                            <span class="text-sm font-medium text-gray-700">{{ type }}</span>
                         </label>
                    </div>
                 </div>

                 <!-- Basic Info -->
                 <div class="space-y-4">
                    <BaseSelect 
                        v-model="editForm.crop_variation" 
                        :options="cropOptions" 
                        label="Crop Variation" 
                    />
                    
                    <BaseInput 
                        v-model="editForm.title" 
                        label="Title *" 
                        required
                    />

                    <BaseInput 
                        v-model="editForm.author" 
                        label="Author(s) *" 
                        required
                    />
                 </div>
              </div>

              <!-- Dates -->
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                 <BaseInput v-model="editForm.publication_date" type="date" label="Publication Date" />
                 <BaseInput v-model="editForm.start_date" type="date" label="Date Started" />
                 <BaseInput v-model="editForm.deadline_date" type="date" label="Deadline" />
              </div>

              <!-- Publishing Details -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                 <BaseInput v-model="editForm.publisher" label="Publisher" />
                 <BaseInput v-model="editForm.edition" label="Edition" />
                 <BaseInput v-model="editForm.physical_description" label="Physical Desc" />
                 <BaseInput v-model="editForm.isbn_issn" label="ISBN / ISSN" />
              </div>

              <div class="space-y-1">
                 <label class="block text-xs font-bold text-gray-700 mb-1 ml-1">Subject(s)</label>
                 <textarea v-model="editForm.subjects" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition text-sm shadow-sm" placeholder="Keywords..." rows="2"></textarea>
              </div>

              <!-- Location & Condition -->
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                 <BaseInput v-model="editForm.shelf_location" label="Shelf Location" />
                 <BaseSelect v-model="editForm.item_condition" :options="conditionOptions" label="Condition" />
                 <BaseInput v-model="editForm.link" type="url" label="Link" />
              </div>

              <!-- File Upload -->
              <div class="bg-gray-100 p-4 rounded-xl border border-dashed border-gray-300 hover:bg-gray-200/50 transition-colors">
                 <label class="block text-xs font-bold text-gray-500 uppercase mb-2">
                    Replace File (Optional)
                 </label>
                 <input 
                    type="file" 
                    @change="handleFileChange" 
                    accept=".pdf, .jpg, .jpeg, .png" 
                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-gray-600 file:text-white hover:file:bg-gray-700 cursor-pointer"
                 />
              </div>

            </form>
          </div>

          <div class="bg-gray-50 p-4 border-t border-gray-100 flex justify-end gap-3 shrink-0">
              <BaseButton 
                @click="isEditModalOpen = false" 
                variant="ghost"
              >
                Cancel
              </BaseButton>

              <BaseButton 
                @click="saveEdit" 
                :disabled="isSaving" 
                variant="primary"
                class="min-w-[120px]"
              >
                  {{ isSaving ? 'Saving...' : 'Update Item' }}
              </BaseButton>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Archive Confirmation Modal (Reused Logic) -->
    <Transition name="pop">
      <div v-if="confirmModal.show" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden transform transition-all p-8 text-center animate-pop">
          <div class="mb-6 flex justify-center">
             <div class="w-20 h-20 rounded-full bg-gray-50 flex items-center justify-center text-5xl shadow-inner">
              {{ 
                confirmModal.action === 'Archive' ? '🗑️' : 
                confirmModal.action === 'Reject' ? '❌' : 
                confirmModal.action === 'Approve' ? '✅' : '♻️' 
              }}
             </div>
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-2">{{ confirmModal.title }}</h3>
          <p class="text-gray-500 text-sm mb-6 leading-relaxed">{{ confirmModal.subtext }}</p>
          <div class="flex gap-3 justify-center">
            <BaseButton @click="confirmModal.show = false" variant="ghost">Cancel</BaseButton>
            <BaseButton 
              @click="executeArchive" 
              :disabled="confirmModal.isProcessing"
              :variant="['Archive', 'Reject'].includes(confirmModal.action) ? 'danger' : 'primary'"
              class="shadow-lg"
            >
              Yes, {{ confirmModal.action }}
            </BaseButton>
          </div>
        </div>
      </div>
    </Transition>

    <!-- View Details Modal -->
    <Transition name="fade">
      <div v-if="selectedItem" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/80 backdrop-blur-sm overflow-y-auto" @click.self="closeDetails">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl max-h-[95vh] flex flex-col overflow-hidden animate-pop">
          
          <div class="bg-emerald-900 text-white p-6 flex justify-between items-start shrink-0">
            <div>
              <div class="flex gap-2 mb-2">
                 <span class="bg-emerald-800 text-emerald-100 text-[10px] uppercase font-bold px-2 py-1 rounded inline-block border border-emerald-700">{{ selectedItem.knowledge_type }}</span>
                 <span class="bg-white/20 text-white text-[10px] uppercase font-bold px-2 py-1 rounded inline-block border border-white/30">{{ selectedItem.status }}</span>
              </div>
              <h2 class="text-2xl font-bold leading-tight line-clamp-2 max-w-2xl">{{ selectedItem.title }}</h2>
              <p class="text-emerald-200 text-sm mt-1 font-medium">Author: {{ selectedItem.author }}</p>
            </div>
            <button @click="closeDetails" class="text-white/70 hover:text-white transition w-10 h-10 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 text-2xl font-bold leading-none">&times;</button>
          </div>

          <div class="flex-1 overflow-y-auto p-6 bg-gray-50 custom-scrollbar">
              
             <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Details Card -->
                <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm space-y-4 h-full">
                   <h3 class="font-bold text-gray-800 border-b pb-3 flex items-center gap-2"><span>📖</span> Catalog Details</h3>
                   <div class="grid grid-cols-3 gap-y-3 gap-x-2 text-sm">
                      <span class="text-gray-500 font-medium">Publisher:</span> <span class="col-span-2 text-gray-800">{{ selectedItem.publisher || '-' }}</span>
                      <span class="text-gray-500 font-medium">Edition:</span> <span class="col-span-2 text-gray-800">{{ selectedItem.edition || '-' }}</span>
                      <span class="text-gray-500 font-medium">Date:</span> <span class="col-span-2 text-gray-800">{{ formatDate(selectedItem.publication_date) }}</span>
                      <span class="text-gray-500 font-medium">ISBN/ISSN:</span> <span class="col-span-2 font-mono text-gray-600 bg-gray-50 px-1 rounded w-fit">{{ selectedItem.isbn_issn || '-' }}</span>
                      <span class="text-gray-500 font-medium">Desc:</span> <span class="col-span-2 text-gray-800">{{ selectedItem.physical_description || '-' }}</span>
                   </div>
                </div>

                <!-- Location Card -->
                <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm space-y-4 h-full">
                   <h3 class="font-bold text-gray-800 border-b pb-3 flex items-center gap-2"><span>📍</span> Location & Topic</h3>
                   <div class="grid grid-cols-3 gap-y-3 gap-x-2 text-sm">
                      <span class="text-gray-500 font-medium">Shelf Loc:</span> <span class="col-span-2 font-mono font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded w-fit">{{ selectedItem.shelf_location || 'Unknown' }}</span>
                      <span class="text-gray-500 font-medium">Condition:</span> <span class="col-span-2 text-gray-800">{{ selectedItem.item_condition }}</span>
                      <span class="text-gray-500 font-medium">Crop:</span> <span class="col-span-2 text-amber-700 font-bold bg-amber-50 px-2 py-0.5 rounded w-fit">{{ selectedItem.crop_variation || 'General' }}</span>
                      <span class="text-gray-500 font-medium">Subjects:</span> <span class="col-span-2 italic text-gray-500 text-xs leading-relaxed border-l-2 pl-2 border-gray-200">{{ selectedItem.subjects || 'No keywords' }}</span>
                   </div>
                </div>
             </div>

             <!-- Digital Preview -->
             <div v-if="selectedItem.file_path || selectedItem.link" class="bg-blue-50/50 p-6 rounded-xl border border-blue-100">
                <h3 class="font-bold text-blue-900 mb-4 flex items-center gap-2"><span>🌐</span> Digital Access</h3>
                
                <div class="flex flex-col gap-4">
                  
                  <div v-if="selectedItem.file_path" class="w-full">
                      <div class="flex justify-between items-center mb-2">
                         <p class="text-xs text-blue-600 font-bold uppercase tracking-wide">Attached Document Preview</p>
                         <button @click="toggleFullscreen" class="text-xs flex items-center gap-1 bg-white border border-blue-200 text-blue-600 px-3 py-1.5 rounded-lg hover:bg-blue-50 font-bold transition shadow-sm">
                           ⛶ Full Screen
                         </button>
                      </div>
                      
                      <div ref="pdfContainer" class="w-full bg-gray-900 rounded-xl overflow-hidden shadow-lg h-[600px] border border-gray-200">
                          <iframe 
                             :src="`${ASSET_URL}/uploads/${selectedItem.file_path}`" 
                             class="w-full h-full border-none bg-white" 
                             title="PDF Preview">
                          </iframe>
                      </div>
                   </div>

                   <div v-if="selectedItem.link" class="w-full mt-2">
                      <a :href="selectedItem.link" target="_blank" class="flex items-center justify-center gap-2 w-full bg-blue-600 text-white font-bold py-3 rounded-xl shadow-lg hover:bg-blue-700 hover:scale-[1.01] transition-all">
                         <span>🔗 Open External Link / Website</span>
                      </a>
                   </div>
                </div>
             </div>
             <div v-else class="text-center py-12 text-gray-400 italic bg-white rounded-xl border-dashed border-2 border-gray-200">
                <div class="text-4xl opacity-20 mb-2">📄</div>
                No digital copy available for this item.
             </div>

          </div>

          <!-- Modal Footer with Actions -->
          <div v-if="selectedItem.status === 'pending'" class="bg-gray-50 p-4 border-t border-gray-100 flex justify-end gap-3 shrink-0">
             <BaseButton 
               @click="rejectResearch(selectedItem.id)" 
               variant="danger"
               class="!bg-white !text-red-600 !border !border-red-200 hover:!bg-red-50"
             >
               ❌ Reject
             </BaseButton>
             <BaseButton 
               @click="approveResearch(selectedItem.id)" 
               variant="primary"
             >
               ✅ Approve
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

.animate-spin-refresh {
  animation: spin-refresh 0.6s ease-in-out;
}
@keyframes spin-refresh {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
</style>
