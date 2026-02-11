<script setup lang="ts">
import { useMasterlist } from '../composables/useMasterlist'

const {
  isLoading, isRefreshing, searchQuery, statusFilter,
  currentPage, itemsPerPage, filteredItems, paginatedItems, totalPages,
  nextPage, prevPage,
  isEditModalOpen, isSaving, editForm,
  openEdit, handleFileChange, saveEdit,
  getStatusBadge, formatDate, resetFilters
} = useMasterlist()
</script>

<template>
  <div class="p-6 bg-gray-50 min-h-screen">

    <!-- Header -->
    <div class="mb-6 flex flex-col sm:flex-row justify-between items-center gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">📋 Masterlist</h1>
        <p class="text-sm text-gray-500">View and edit all knowledge products across every status.</p>
      </div>
      <span class="text-sm text-gray-400">{{ filteredItems.length }} entries found</span>
    </div>

    <!-- Filters Toolbar -->
    <div class="bg-white p-4 rounded-lg shadow mb-4 flex flex-wrap gap-4 items-center">
      <!-- Search -->
      <div class="relative flex-1 min-w-[200px]">
        <input 
          v-model="searchQuery"
          type="text" 
          placeholder="Search title, author, crop..." 
          class="pl-10 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500 outline-none w-full"
        >
        <span class="absolute left-3 top-2.5 text-gray-400">🔍</span>
      </div>

      <!-- Status Filter -->
      <div class="flex items-center gap-2">
        <span class="text-sm text-gray-600 font-medium">Status:</span>
        <select v-model="statusFilter" class="border rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-green-500">
          <option value="ALL">All Statuses</option>
          <option value="APPROVED">Published</option>
          <option value="PENDING">Pending</option>
          <option value="REJECTED">Rejected</option>
        </select>
      </div>

      <!-- Refresh -->
      <button 
        @click="resetFilters"
        class="p-2 bg-green-100 text-green-700 border border-green-200 rounded-lg hover:bg-green-200 hover:text-green-800 transition-all shadow-sm"
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
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Title</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Author</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Status</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Type</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Date</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-if="isLoading">
              <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                <div class="flex justify-center items-center gap-2">
                  <span class="animate-spin text-xl">⏳</span> Loading masterlist...
                </div>
              </td>
            </tr>

            <tr v-else-if="filteredItems.length === 0">
              <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                No research entries found matching your filters.
              </td>
            </tr>

            <tr 
              v-for="item in paginatedItems" :key="item.id" 
              class="hover:brightness-95 transition border-l-4"
              :class="{
                'bg-green-50 border-l-green-400': item.status === 'approved',
                'bg-yellow-50 border-l-yellow-400': item.status === 'pending',
                'bg-red-50 border-l-red-400': item.status === 'rejected',
                'bg-white border-l-gray-200': !['approved','pending','rejected'].includes(item.status)
              }"
            >
              <td class="px-6 py-4">
                <div class="font-medium text-gray-900 max-w-[250px] truncate" :title="item.title">{{ item.title }}</div>
                <div v-if="item.crop_variation" class="text-xs text-gray-400 mt-0.5">{{ item.crop_variation }}</div>
              </td>
              <td class="px-6 py-4 text-sm text-gray-700">{{ item.author }}</td>
              <td class="px-6 py-4">
                <span :class="['px-2 py-1 text-xs font-bold rounded-full border', getStatusBadge(item.status).classes]">
                  {{ getStatusBadge(item.status).label }}
                </span>
              </td>
              <td class="px-6 py-4 text-sm text-gray-500">{{ item.knowledge_type || '—' }}</td>
              <td class="px-6 py-4 text-sm text-gray-500">{{ formatDate(item.created_at) }}</td>
              <td class="px-6 py-4 text-right">
                <button 
                  @click="openEdit(item)" 
                  class="text-xs bg-green-100 text-green-700 px-3 py-1.5 rounded font-bold hover:bg-green-200 transition border border-green-200"
                >
                  ✏️ Edit
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="filteredItems.length > itemsPerPage" class="mt-4 flex justify-between items-center px-4">
      <span class="text-sm text-gray-600">
        Showing {{ ((currentPage - 1) * itemsPerPage) + 1 }} to {{ Math.min(currentPage * itemsPerPage, filteredItems.length) }} of {{ filteredItems.length }} entries
      </span>
      <div class="flex gap-2">
        <button 
          @click="prevPage" 
          :disabled="currentPage === 1"
          class="px-3 py-1 text-sm font-bold rounded-lg border bg-white hover:bg-gray-50 disabled:opacity-50 transition"
        >
          Previous
        </button>
        <span class="px-3 py-1 text-sm font-bold bg-green-50 text-green-700 rounded-lg border border-green-200">
          Page {{ currentPage }} of {{ totalPages }}
        </span>
        <button 
          @click="nextPage" 
          :disabled="currentPage >= totalPages"
          class="px-3 py-1 text-sm font-bold rounded-lg border bg-white hover:bg-gray-50 disabled:opacity-50 transition"
        >
          Next
        </button>
      </div>
    </div>

    <!-- Edit Modal -->
    <Transition name="modal-pop">
      <div v-if="isEditModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-75 backdrop-blur-sm overflow-y-auto">
        <div class="bg-white rounded-xl w-full max-w-4xl overflow-hidden shadow-2xl transform transition-all flex flex-col max-h-[90vh]">
          
          <!-- Modal Header -->
          <div class="bg-green-700 text-white p-4 flex justify-between items-center shrink-0">
            <h2 class="font-bold text-lg">✏️ Edit Knowledge Product</h2>
            <button @click="isEditModalOpen = false" class="text-green-100 hover:text-white text-2xl font-bold transition-transform hover:rotate-90">&times;</button>
          </div>

          <!-- Modal Body -->
          <div class="p-6 overflow-y-auto custom-scrollbar">
            <form @submit.prevent="saveEdit" class="space-y-4">

              <!-- Row 1: Type + Crop Variation -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Type <span class="text-red-500">*</span></label>
                  <div class="flex flex-col gap-2 p-2 border rounded bg-white max-h-32 overflow-y-auto">
                    <label class="flex items-center gap-2">
                      <input type="checkbox" v-model="editForm.knowledge_type" value="Research Paper" class="accent-green-600">
                      <span class="text-sm">Research Paper</span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input type="checkbox" v-model="editForm.knowledge_type" value="Book" class="accent-green-600">
                      <span class="text-sm">Book</span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input type="checkbox" v-model="editForm.knowledge_type" value="Journal" class="accent-green-600">
                      <span class="text-sm">Journal</span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input type="checkbox" v-model="editForm.knowledge_type" value="IEC Material" class="accent-green-600">
                      <span class="text-sm">IEC Material</span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input type="checkbox" v-model="editForm.knowledge_type" value="Thesis" class="accent-green-600">
                      <span class="text-sm">Thesis</span>
                    </label>
                  </div>
                </div>
                <div>
                  <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Crop Variation (Optional)</label>
                  <select v-model="editForm.crop_variation" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500 outline-none bg-white">
                    <option value="" disabled>Select Variation</option>
                    <option>Sweet Potato</option>
                    <option>Potato</option>
                    <option>Yam Aeroponics</option>
                    <option>Yam Minisetts</option>
                    <option>Taro</option>
                    <option>Cassava</option>
                    <option>Yacon</option>
                    <option>Ginger</option>
                    <option>Canna</option>
                    <option>Arrowroot</option>
                    <option>Turmeric</option>
                    <option>Tannia</option>
                    <option>Kinampay</option>
                    <option>Zambal</option>
                    <option>Bengueta</option>
                    <option>Immitlog</option>
                    <option>Beniazuma</option>
                    <option>Haponita</option>
                    <option>Ganza</option>
                    <option>Montanosa</option>
                    <option>Igorota</option>
                    <option>Solibao</option>
                    <option>Raniag</option>
                    <option>Dalisay</option>
                    <option>Others</option>
                  </select>
                </div>
              </div>

              <!-- Title -->
              <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Title / Name of Product <span class="text-red-500">*</span></label>
                <input v-model="editForm.title" type="text" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500 outline-none" placeholder="Enter title" required />
              </div>

              <!-- Author + Publication Date -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Author(s) <span class="text-red-500">*</span></label>
                  <input v-model="editForm.author" type="text" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500 outline-none" placeholder="e.g. Juan Cruz" required />
                </div>
                <div>
                  <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Publication / Creation Date</label>
                  <input v-model="editForm.publication_date" type="date" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500 outline-none" />
                </div>
              </div>

              <!-- Start Date + Deadline -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-gray-50 p-3 rounded border border-gray-200">
                <div>
                  <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Date Started (Optional)</label>
                  <input v-model="editForm.start_date" type="date" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500 outline-none bg-white" />
                </div>
                <div>
                  <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Deadline Date (Optional)</label>
                  <input v-model="editForm.deadline_date" type="date" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500 outline-none bg-white" />
                </div>
              </div>

              <!-- Publisher + Edition -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Publisher / Producer</label>
                  <input v-model="editForm.publisher" type="text" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500 outline-none" />
                </div>
                <div>
                  <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Edition (Optional)</label>
                  <input v-model="editForm.edition" type="text" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500 outline-none" placeholder="e.g. 2nd Edition" />
                </div>
              </div>

              <!-- Physical Description + ISBN -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Physical Description</label>
                  <input v-model="editForm.physical_description" type="text" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500 outline-none" placeholder="e.g. 150 pages" />
                </div>
                <div>
                  <label class="block text-xs font-bold text-gray-500 uppercase mb-1">ISBN / ISSN</label>
                  <input v-model="editForm.isbn_issn" type="text" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500 outline-none" />
                </div>
              </div>

              <!-- Subjects -->
              <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Subject(s) / Keywords</label>
                <textarea v-model="editForm.subjects" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500 outline-none" placeholder="Keywords describing content..." rows="2"></textarea>
              </div>

              <!-- Shelf + Condition + Link -->
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Shelf Location</label>
                  <input v-model="editForm.shelf_location" type="text" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500 outline-none" placeholder="e.g. Shelf A-1" />
                </div>
                <div>
                  <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Condition</label>
                  <select v-model="editForm.item_condition" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500 outline-none bg-white">
                    <option>New</option>
                    <option>Good</option>
                    <option>Fair</option>
                    <option>Poor</option>
                    <option>Damaged</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Link (Optional)</label>
                  <input v-model="editForm.link" type="url" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500 outline-none" placeholder="https://..." />
                </div>
              </div>

              <!-- File Upload -->
              <div class="bg-gray-50 p-4 rounded border border-dashed border-gray-300">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Replace File (Optional)</label>
                <input 
                  type="file" 
                  @change="handleFileChange" 
                  accept=".pdf, .jpg, .jpeg, .png" 
                  class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-green-100 file:text-green-700 hover:file:bg-green-200 cursor-pointer"
                />
              </div>

            </form>
          </div>

          <!-- Modal Footer -->
          <div class="bg-gray-50 p-4 border-t flex justify-end gap-3 shrink-0">
            <button 
              @click="isEditModalOpen = false" 
              class="px-5 py-2 rounded-lg font-bold text-gray-600 bg-white border border-gray-200 shadow-sm hover:bg-gray-100 hover:text-gray-800 transition"
            >
              Cancel
            </button>
            <button 
              @click="saveEdit" 
              :disabled="isSaving" 
              class="px-6 py-2 rounded-lg font-bold text-white bg-green-600 shadow-md hover:bg-green-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ isSaving ? 'Saving...' : 'Update Item 💾' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>

  </div>
</template>

<style scoped src="../assets/styles/Masterlist.css"></style>
<style scoped>
@keyframes spin-refresh {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
.animate-spin-refresh {
  animation: spin-refresh 0.6s ease-in-out;
}
</style>
