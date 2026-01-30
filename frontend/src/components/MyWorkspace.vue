<script setup lang="ts">
import { ref } from 'vue' 
import SubmittedResearches from './SubmittedResearches.vue'
import { useMyWorkspace, type User } from '../composables/useMyWorkspace'

// Define Props
const props = defineProps<{
  currentUser: User | null
}>()

// Use Composable
const { 
  activeTab, 
  uploadModal, 
  isUploading, 
  todayStr, 
  handleUploadFile, 
  submitResearch 
} = useMyWorkspace(props.currentUser)

// Reference to Child Component to trigger refresh
const submissionsRef = ref<InstanceType<typeof SubmittedResearches> | null>(null)

// Wrapper function to pass the refresh callback
const handleSubmit = () => {
  submitResearch(() => {
    if (submissionsRef.value) {
      submissionsRef.value.fetchData()
    }
  })
}
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
            @click="uploadModal.show = true" 
            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md font-bold text-sm flex items-center gap-2 shadow hover:shadow-lg transform transition-all duration-200 hover:scale-105 active:scale-95"
        >
          <span>➕</span> Submit New
        </button>
      </div>
    </div>

    <div class="flex space-x-4 border-b mb-6">
      <button @click="activeTab = 'submitted'" :class="`pb-2 px-4 font-medium text-sm transition ${activeTab === 'submitted' ? 'border-b-2 border-green-600 text-green-700' : 'text-gray-500 hover:text-gray-700'}`">📄 Submitted Researches</button>
      <button @click="activeTab = 'archived'" :class="`pb-2 px-4 font-medium text-sm transition ${activeTab === 'archived' ? 'border-b-2 border-red-500 text-red-600' : 'text-gray-500 hover:text-gray-700'}`">🗑️ Archived Files</button>
    </div>

    <SubmittedResearches ref="submissionsRef" :currentUser="currentUser" :isArchived="activeTab === 'archived'" />

    <Transition name="modal-pop">
      <div v-if="uploadModal.show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-75 backdrop-blur-sm">
        <div class="bg-white rounded-xl w-full max-w-lg overflow-hidden shadow-2xl transform transition-all">
          
          <div class="bg-green-700 text-white p-4 flex justify-between items-center">
              <h2 class="font-bold text-lg">📤 Submit Research</h2>
              <button @click="uploadModal.show = false" class="text-green-100 hover:text-white text-2xl font-bold transition-transform hover:rotate-90">&times;</button>
          </div>
          
          <div class="p-6 space-y-4">
              <div>
                <label class="block text-xs font-bold text-gray-500 mb-1">Research Title <span class="text-red-500">*</span></label>
                <input v-model="uploadModal.title" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500 outline-none transition" placeholder="Enter title" />
              </div>

              <div>
                <label class="block text-xs font-bold text-gray-500 mb-1">Author Name <span class="text-red-500">*</span></label>
                <input v-model="uploadModal.author" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500 outline-none transition" placeholder="Enter author" />
              </div>
              
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-bold text-gray-500 mb-1">Date Started</label>
                  <input 
                    v-model="uploadModal.start_date" 
                    type="date" 
                    :max="todayStr"
                    class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500 outline-none transition" 
                  />
                </div>
                <div>
                  <label class="block text-xs font-bold text-gray-500 mb-1">Deadline <span class="text-red-500">*</span></label>
                  <input 
                    v-model="uploadModal.deadline_date" 
                    type="date" 
                    :min="uploadModal.start_date"
                    class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500 outline-none transition" 
                  />
                </div>
              </div>

              <div>
                <label class="block text-xs font-bold text-gray-500 mb-1">Abstract</label>
                <textarea v-model="uploadModal.abstract" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500 outline-none transition" placeholder="Short description..." rows="3"></textarea>
              </div>
              
              <div>
                <label class="block text-xs font-bold text-gray-500 mb-1">Upload File (PDF/Image) <span class="text-red-500">*</span></label>
                <input 
                  type="file" 
                  @change="handleUploadFile" 
                  class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100 hover:file:scale-105 file:transition-all file:duration-200 file:cursor-pointer"
                  accept=".pdf, .jpg, .jpeg, .png" 
                />
              </div>
          </div>

          <div class="bg-gray-50 p-4 border-t flex justify-end gap-3">
              <button 
                @click="uploadModal.show = false" 
                class="px-5 py-2 rounded-lg font-bold text-gray-600 bg-white border border-gray-200 shadow-sm hover:bg-gray-50 hover:shadow-md hover:text-gray-800 transform transition-all duration-200 hover:scale-105 active:scale-95"
              >
                Cancel
              </button>

              <button 
                @click="handleSubmit" 
                :disabled="isUploading" 
                class="px-6 py-2 rounded-lg font-bold text-white bg-green-600 shadow-md hover:bg-green-700 hover:shadow-lg transform transition-all duration-200 hover:scale-105 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
              >
                  {{ isUploading ? 'Uploading...' : 'Submit 🚀' }}
              </button>
          </div>
        </div>
      </div>
    </Transition>

  </div>
</template>

<style scoped src="../assets/styles/MyWorkspace.css"></style>