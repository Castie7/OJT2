<script setup lang="ts">
import { ref } from 'vue' 
import SubmittedResearches from './SubmittedResearches.vue'
import { useMyWorkspace, type User } from '../composables/useMyWorkspace' 
import ResearchDetailsModal from './ResearchDetailsModal.vue'

const props = defineProps<{
  currentUser: User | null
}>()

const { 
  activeTab, 
  isModalOpen, 
  isSubmitting, 
  form, 
  openSubmitModal, 
  openEditModal,
  submitResearch,
  handleFileChange
} = useMyWorkspace(props.currentUser)

// Reference to the child component (the list of researches)
// This allows us to call methods inside SubmittedResearches.vue
const submissionsRef = ref<InstanceType<typeof SubmittedResearches> | null>(null)
const selectedResearch = ref(null)

const handleViewResearch = (item: any) => {
  selectedResearch.value = item
}

const handleSubmit = async () => {
  const success = await submitResearch()
  if (success) {
      // Refresh the list after a successful submission
      if (submissionsRef.value) {
        submissionsRef.value.fetchData()
      }
  }
}

// --- HANDLE NOTIFICATION CLICKS ---
// This is called by Dashboard.vue when a user clicks a notification
const openNotification = (id: number) => {
    // Pass the ID down to the inner component to open the comments
    if (submissionsRef.value) {
        submissionsRef.value.openNotification(id)
    }
}

// Expose this function to the parent (Dashboard.vue)
defineExpose({ openNotification })
</script>

<template>
  <div class="bg-white p-6 rounded-lg shadow-lg min-h-[500px]">
    
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 border-b pb-4 gap-4">
      <div>
        <h2 class="text-xl font-bold text-gray-800">👤 My Workspace</h2>
        <p class="text-sm text-gray-500">Managing uploads for: <span class="font-bold text-green-700">{{ currentUser?.name }}</span></p>
      </div>
      <div class="flex items-center gap-4">
        <button 
            @click="openSubmitModal" 
            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md font-bold text-sm flex items-center gap-2 shadow hover:shadow-lg transform transition-all duration-200 hover:scale-105 active:scale-95"
        >
          <span>➕</span> Submit New Item
        </button>
      </div>
    </div>

    <div class="flex space-x-4 border-b mb-6">
      <button @click="activeTab = 'submitted'" :class="`pb-2 px-4 font-medium text-sm transition ${activeTab === 'submitted' ? 'border-b-2 border-green-600 text-green-700' : 'text-gray-500 hover:text-gray-700'}`">📄 Submitted Researches</button>
      <button @click="activeTab = 'archived'" :class="`pb-2 px-4 font-medium text-sm transition ${activeTab === 'archived' ? 'border-b-2 border-red-500 text-red-600' : 'text-gray-500 hover:text-gray-700'}`">🗑️ Archived Files</button>
    </div>

    <SubmittedResearches 
        ref="submissionsRef" 
        :currentUser="currentUser" 
        :isArchived="activeTab === 'archived'" 
        @edit="openEditModal"
        @view="handleViewResearch"
    />

    <Transition name="modal-pop">
      <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-75 backdrop-blur-sm overflow-y-auto">
        <div class="bg-white rounded-xl w-full max-w-4xl overflow-hidden shadow-2xl transform transition-all flex flex-col max-h-[90vh]">
          
          <div class="bg-green-700 text-white p-4 flex justify-between items-center shrink-0">
              <h2 class="font-bold text-lg">
                  {{ form.id ? '✏️ Edit Knowledge Product' : '📤 Submit Knowledge Product' }}
              </h2>
              <button @click="isModalOpen = false" class="text-green-100 hover:text-white text-2xl font-bold transition-transform hover:rotate-90">&times;</button>
          </div>
          
          <div class="p-6 overflow-y-auto custom-scrollbar">
            <form @submit.prevent="handleSubmit" class="space-y-4">
              
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                 <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Type <span class="text-red-500">*</span></label>
                    <div class="flex flex-col gap-2 p-2 border rounded bg-white max-h-32 overflow-y-auto">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" v-model="form.knowledge_type" value="Research Paper" class="accent-green-600">
                            <span class="text-sm">Research Paper</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" v-model="form.knowledge_type" value="Book" class="accent-green-600">
                            <span class="text-sm">Book</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" v-model="form.knowledge_type" value="Journal" class="accent-green-600">
                            <span class="text-sm">Journal</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" v-model="form.knowledge_type" value="IEC Material" class="accent-green-600">
                            <span class="text-sm">IEC Material</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" v-model="form.knowledge_type" value="Thesis" class="accent-green-600">
                            <span class="text-sm">Thesis</span>
                        </label>
                    </div>
                 </div>
                 <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Crop Variation (Optional)</label>
                    <select v-model="form.crop_variation" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500 outline-none bg-white">
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

              <div>
                 <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Title / Name of Product <span class="text-red-500">*</span></label>
                 <input v-model="form.title" type="text" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500 outline-none" placeholder="Enter title" required />
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                 <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Author(s) <span class="text-red-500">*</span></label>
                    <input v-model="form.author" type="text" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500 outline-none" placeholder="e.g. Juan Cruz" required />
                 </div>
                 <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Publication / Creation Date</label>
                    <input v-model="form.publication_date" type="date" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500 outline-none" />
                 </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-gray-50 p-3 rounded border border-gray-200">
                 <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Date Started (Optional)</label>
                    <input v-model="form.start_date" type="date" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500 outline-none bg-white" />
                 </div>
                 <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Deadline Date (Optional)</label>
                    <input v-model="form.deadline_date" type="date" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500 outline-none bg-white" />
                 </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                 <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Publisher / Producer</label>
                    <input v-model="form.publisher" type="text" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500 outline-none" />
                 </div>
                 <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Edition (Optional)</label>
                    <input v-model="form.edition" type="text" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500 outline-none" placeholder="e.g. 2nd Edition" />
                 </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                 <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Physical Description</label>
                    <input v-model="form.physical_description" type="text" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500 outline-none" placeholder="e.g. 150 pages" />
                 </div>
                 <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">ISBN / ISSN</label>
                    <input v-model="form.isbn_issn" type="text" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500 outline-none" />
                 </div>
              </div>

              <div>
                 <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Subject(s) / Keywords</label>
                 <textarea v-model="form.subjects" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500 outline-none" placeholder="Keywords describing content..." rows="2"></textarea>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                 <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Shelf Location</label>
                    <input v-model="form.shelf_location" type="text" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500 outline-none" placeholder="e.g. Shelf A-1" />
                 </div>
                 <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Condition</label>
                    <select v-model="form.item_condition" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500 outline-none bg-white">
                      <option>New</option>
                      <option>Good</option>
                      <option>Fair</option>
                      <option>Poor</option>
                      <option>Damaged</option>
                    </select>
                 </div>
                 <div>
                      <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Link (Optional)</label>
                      <input v-model="form.link" type="url" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500 outline-none" placeholder="https://..." />
                 </div>
              </div>

              <div class="bg-gray-50 p-4 rounded border border-dashed border-gray-300">
                 <label class="block text-xs font-bold text-gray-500 uppercase mb-2">
                    {{ form.id ? 'Replace File (Optional)' : 'Upload File (PDF/Image) (Optional)' }}
                 </label>
                 <input 
                    type="file" 
                    @change="handleFileChange" 
                    accept=".pdf, .jpg, .jpeg, .png" 
                    class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-green-100 file:text-green-700 hover:file:bg-green-200 cursor-pointer"
                 />
              </div>

            </form>
          </div>

          <div class="bg-gray-50 p-4 border-t flex justify-end gap-3 shrink-0">
              <button 
                @click="isModalOpen = false" 
                class="px-5 py-2 rounded-lg font-bold text-gray-600 bg-white border border-gray-200 shadow-sm hover:bg-gray-100 hover:text-gray-800 transition"
              >
                Cancel
              </button>

              <button 
                @click="handleSubmit" 
                :disabled="isSubmitting" 
                class="px-6 py-2 rounded-lg font-bold text-white bg-green-600 shadow-md hover:bg-green-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
              >
                  {{ isSubmitting ? 'Saving...' : (form.id ? 'Update Item 💾' : 'Submit 🚀') }}
              </button>
          </div>
        </div>
      </div>
    </Transition>

    <ResearchDetailsModal 
      :research="selectedResearch" 
      @close="selectedResearch = null" 
    />
  </div>
</template>

<style scoped src="../assets/styles/MyWorkspace.css"></style>