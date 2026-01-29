<script setup>
import { ref } from 'vue' 
import SubmittedResearches from './SubmittedResearches.vue'

const props = defineProps(['currentUser'])

// State
const activeTab = ref('submitted') 
const uploadModal = ref({ show: false, title: '', author: '', abstract: '', start_date: '', deadline_date: '', file: null })
const isUploading = ref(false)
const submissionsRef = ref(null)

// --- HELPERS ---
const getHeaders = () => {
  const token = document.cookie.split('; ').find(row => row.startsWith('auth_token='))?.split('=')[1];
  return { 'Authorization': token };
}

// Get Today's date for "max" attributes
const todayStr = new Date().toISOString().split('T')[0];

// --- UPLOAD LOGIC ---
const handleUploadFile = (e) => { 
  const file = e.target.files[0]
  
  // 1. If user cancels file dialog, clear state
  if (!file) {
    uploadModal.value.file = null
    return
  }

  // 2. CHECK FILE EXTENSION & TYPE (More Robust)
  const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
  const fileExtension = file.name.split('.').pop().toLowerCase();
  
  // Check if extension is valid
  if (!allowedExtensions.includes(fileExtension)) {
    alert("❌ Invalid File!\nPlease upload a PDF or an Image (JPG/PNG).")
    e.target.value = '' // Clear the input visually
    uploadModal.value.file = null
    return
  }

  // If valid, save it
  uploadModal.value.file = file 
}

const submitResearch = async () => {
  const form = uploadModal.value;

  // 1. BASIC FIELDS CHECK
  if (!form.title.trim()) { alert("⚠️ Title is required."); return; }
  if (!form.author.trim()) { alert("⚠️ Author is required."); return; }
  if (!form.deadline_date) { alert("⚠️ Deadline Date is required."); return; }
  if (!form.file) { alert("⚠️ File is missing.\nPlease select a PDF or Image."); return; }

  // 2. STRICT DATE VALIDATION
  const start = form.start_date ? new Date(form.start_date) : null;
  const deadline = new Date(form.deadline_date);
  
  // A. Check Year Range (e.g., prevent year 11111)
  const minYear = 2000;
  const maxYear = 2100;

  if (deadline.getFullYear() < minYear || deadline.getFullYear() > maxYear) {
     alert(`⚠️ Invalid Date: Year must be between ${minYear} and ${maxYear}.`);
     return;
  }

  if (start) {
    if (start.getFullYear() < minYear || start.getFullYear() > maxYear) {
        alert(`⚠️ Invalid Start Date: Year must be between ${minYear} and ${maxYear}.`);
        return;
    }
    // B. Check Logical Order
    if (deadline < start) {
      alert("⚠️ Date Error: Deadline cannot be before Start Date.");
      return;
    }
  }

  // 3. PROCEED TO UPLOAD
  isUploading.value = true
  const formData = new FormData()
  formData.append('title', form.title)
  formData.append('author', form.author)
  formData.append('abstract', form.abstract)
  formData.append('start_date', form.start_date)
  formData.append('deadline_date', form.deadline_date)
  formData.append('uploaded_by', props.currentUser.id)
  formData.append('pdf_file', form.file)

  try {
    const res = await fetch('http://localhost:8080/research/create', {
      method: 'POST', headers: getHeaders(), body: formData
    })
    const result = await res.json()
    
    // Handle specific backend validation errors
    if (!res.ok) {
        if (result.messages) {
            const msg = typeof result.messages === 'object' 
                ? Object.values(result.messages).join('\n') 
                : result.messages;
            alert("❌ Submission Failed:\n" + msg);
        } else {
            alert("❌ Error: " + (result.message || "Upload Failed"));
        }
    } else {
      alert("✅ Success! Research Submitted.")
      uploadModal.value = { show: false, title: '', author: '', abstract: '', start_date: '', deadline_date: '', file: null }
      if(submissionsRef.value) submissionsRef.value.fetchData()
    }
  } catch (error) { 
    console.error(error)
    alert("❌ Server Error.") 
  } finally { 
    isUploading.value = false 
  }
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
                @click="submitResearch" 
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

<style scoped>
/* Modal Pop Animation */
.modal-pop-enter-active { animation: pop-in 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
.modal-pop-leave-active { transition: opacity 0.2s ease; }
.modal-pop-leave-to { opacity: 0; }
@keyframes pop-in { 0% { transform: scale(0.9); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
</style>