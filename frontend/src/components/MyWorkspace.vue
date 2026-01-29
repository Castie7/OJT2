<script setup>
import { ref } from 'vue'
import SubmittedResearches from './SubmittedResearches.vue'

const props = defineProps(['currentUser'])

// State
const activeTab = ref('submitted') // 'submitted' or 'archived'
const uploadModal = ref({ show: false, title: '', author: '', abstract: '', file: null })
const isUploading = ref(false)
const submissionsRef = ref(null) // To trigger refresh on child

// --- HELPERS ---
const getHeaders = () => {
  const token = document.cookie.split('; ').find(row => row.startsWith('auth_token='))?.split('=')[1];
  return { 'Authorization': token };
}

// --- UPLOAD LOGIC ---
const handleUploadFile = (e) => { uploadModal.value.file = e.target.files[0] }

const submitResearch = async () => {
  if (!uploadModal.value.title || !uploadModal.value.author || !uploadModal.value.file) {
    alert("Please fill in Title, Author, and select a PDF file.")
    return
  }
  isUploading.value = true
  const formData = new FormData()
  formData.append('title', uploadModal.value.title)
  formData.append('author', uploadModal.value.author)
  formData.append('abstract', uploadModal.value.abstract)
  formData.append('uploaded_by', props.currentUser.id)
  formData.append('pdf_file', uploadModal.value.file)

  try {
    const res = await fetch('http://localhost:8080/research/create', {
      method: 'POST', headers: getHeaders(), body: formData
    })
    const result = await res.json()
    if (res.ok) {
      alert("Success! Your research is now Pending Approval.")
      uploadModal.value = { show: false, title: '', author: '', abstract: '', file: null }
      // Trigger refresh in the child component
      if(submissionsRef.value) submissionsRef.value.fetchData()
    } else { alert("Error: " + (result.message || "Upload Failed")) }
  } catch (error) { alert("Server Error") } finally { isUploading.value = false }
}
</script>

<template>
  <div class="bg-white p-6 rounded-lg shadow-lg min-h-[500px]">
    
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 border-b pb-4 gap-4">
      <div>
        <h2 class="text-xl font-bold text-gray-800">👤 My Workspace</h2>
        <p class="text-sm text-gray-500">Managing uploads for: <span class="font-bold text-green-700">{{ currentUser.name }}</span></p>
      </div>

      <div class="flex items-center gap-4">
         <button @click="uploadModal.show = true" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md font-bold text-sm flex items-center gap-2 shadow transition">
          <span>➕</span> Submit New
        </button>
      </div>
    </div>

    <div class="flex space-x-4 border-b mb-6">
      <button 
        @click="activeTab = 'submitted'"
        :class="`pb-2 px-4 font-medium text-sm transition ${activeTab === 'submitted' ? 'border-b-2 border-green-600 text-green-700' : 'text-gray-500 hover:text-gray-700'}`"
      >
        📄 Submitted Researches
      </button>
      <button 
        @click="activeTab = 'archived'"
        :class="`pb-2 px-4 font-medium text-sm transition ${activeTab === 'archived' ? 'border-b-2 border-red-500 text-red-600' : 'text-gray-500 hover:text-gray-700'}`"
      >
        🗑️ Archived Files
      </button>
    </div>

    <SubmittedResearches 
      ref="submissionsRef"
      :currentUser="currentUser"
      :isArchived="activeTab === 'archived'"
    />

    <div v-if="uploadModal.show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-75">
      <div class="bg-white rounded-lg w-full max-w-lg overflow-hidden shadow-2xl">
        <div class="bg-green-700 text-white p-4 flex justify-between">
            <h2 class="font-bold">📤 Submit Research</h2>
            <button @click="uploadModal.show = false" class="text-green-100 hover:text-white text-2xl font-bold">&times;</button>
        </div>
        <div class="p-6 space-y-4">
            <input v-model="uploadModal.title" class="w-full border p-2 rounded" placeholder="Research Title" />
            <input v-model="uploadModal.author" class="w-full border p-2 rounded" placeholder="Author Name" />
            <textarea v-model="uploadModal.abstract" class="w-full border p-2 rounded" placeholder="Abstract..."></textarea>
            <input type="file" @change="handleUploadFile" class="w-full text-sm" accept="application/pdf" />
        </div>
        <div class="bg-gray-50 p-4 border-t flex justify-end gap-2">
            <button @click="uploadModal.show = false" class="text-gray-500 font-bold px-4">Cancel</button>
            <button @click="submitResearch" :disabled="isUploading" class="bg-green-600 text-white px-6 py-2 rounded font-bold">
                {{ isUploading ? 'Uploading...' : 'Submit' }}
            </button>
        </div>
      </div>
    </div>

  </div>
</template>