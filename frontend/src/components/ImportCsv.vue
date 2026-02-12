<script setup lang="ts">
import { ref } from 'vue'
import api from '../services/api' // ✅ Switch to Secure API Service
import { useToast } from '../composables/useToast'

const emit = defineEmits<{
  (e: 'upload-success'): void
}>()

const fileInput = ref<HTMLInputElement | null>(null)
const selectedFile = ref<File | null>(null)
const isUploading = ref(false)
const uploadStatus = ref<{ message: string, type: 'success' | 'error' | '' }>({ message: '', type: '' })

// 1. Handle File Selection
const handleFileChange = (event: Event) => {
  const target = event.target as HTMLInputElement
  if (target.files && target.files[0]) {
    const file = target.files[0]
    
    // Validate .csv extension
    if (file.type !== 'text/csv' && !file.name.endsWith('.csv')) {
        uploadStatus.value = { message: '❌ Please select a valid .csv file', type: 'error' }
        selectedFile.value = null
        target.value = '' 
        return
    }

    selectedFile.value = file
    uploadStatus.value = { message: '', type: '' }
  }
}

// 2. Download Template Logic (Kept exactly as you had it)
const downloadTemplate = () => {
    const headers = [
        'Title', 'Author', 'Type', 'Date', 'Edition', 'Publisher',
        'Pages', 'ISBN/ISSN', 'Subjects', 'Location', 'Condition', 'Crop'
    ];

    const rows = [
        [
            'Golden Roots Issue No. 01', 'Betty T. Gayao et al.', 'Journal', '2004-01-01', 
            'Vol. 1', 'NPRCRTC - BSU', '16 Pages', 'ISSN 1656-5444', 
            'Sweetpotato processing, Rootcrops', 'Shelf 6b', 'Good', 'Sweetpotato'
        ],
        [
            'Varietal Improvement of Rootcrops', 'Juan Dela Cruz', 'Thesis', '2023-05-15', 
            '1st Edition', 'BSU', '120 Leaves', 'N/A', 
            'Breeding, Genetics', 'Thesis Section', 'Good', 'Cassava'
        ]
    ];
    
    const processRow = (row: string[]) => row.map(val => `"${val}"`).join(',');
    const csvContent = [headers.join(','), ...rows.map(processRow)].join('\n');
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', 'research_upload_template.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// 3. Upload Logic
const uploadCsv = async () => {
    if (!selectedFile.value) return

    isUploading.value = true
    uploadStatus.value = { message: '⏳ Uploading and processing...', type: '' }

    const formData = new FormData()
    formData.append('csv_file', selectedFile.value)

    try {
        // ✅ Use api.post()
        // Axios automatically sets 'Content-Type': 'multipart/form-data' when sending FormData
        // It also handles the Base URL and Auth Cookies automatically.
        const response = await api.post('/research/import-csv', formData)

        const result = response.data

        if (response.status === 200 || response.status === 201) {
            uploadStatus.value = { message: `✅ Success! ${result.count} items imported.`, type: 'success' }
            selectedFile.value = null
            if(fileInput.value) fileInput.value.value = ''
            emit('upload-success') 
        } else {
            uploadStatus.value = { message: `❌ Error: ${result.message}`, type: 'error' }
        }

    } catch (error: any) {
        console.error(error)
        const msg = error.response?.data?.message || 'Server Connection Failed'
        uploadStatus.value = { message: `❌ ${msg}`, type: 'error' }
    } finally {
        isUploading.value = false
    }
}

// 4. Bulk PDF Upload Logic
const pdfInput = ref<HTMLInputElement | null>(null)
const selectedPdfs = ref<File[]>([])
const isPdfUploading = ref(false)
const pdfStatus = ref<{ message: string, type: 'success' | 'error' | '', details?: string[] }>({ message: '', type: '', details: [] })

// Toast State
const { showToast } = useToast()

const handlePdfChange = (event: Event) => {
    const target = event.target as HTMLInputElement
    if (target.files && target.files.length) {
        if (target.files.length > 10) {
            showToast("You can only upload a maximum of 10 files at a time.", "warning")
            target.value = '' // Reset input
            selectedPdfs.value = []
            return
        }
        // Convert FileList to Array
        selectedPdfs.value = Array.from(target.files)
        pdfStatus.value = { message: '', type: '', details: [] }
    }
}

const uploadPdfs = async () => {
    if (!selectedPdfs.value.length) return

    isPdfUploading.value = true
    pdfStatus.value = { message: '⏳ Uploading and linking...', type: '' }

    const formData = new FormData()
    selectedPdfs.value.forEach((file) => {
        formData.append('pdf_files[]', file)
    })
    
    // ... rest of function ... (unchanged logic, just context)


    try {
        const response = await api.post('/research/bulk-upload-pdfs', formData)
        
        let result = response.data
        if (typeof result === 'string') {
            try {
                result = JSON.parse(result)
            } catch (e) {
                console.error("Failed to parse JSON response", e)
            }
        }
        
        console.log("Bulk Upload Result:", result)
        console.log("Full Response:", response)

        if (result.status === 'success' || response.status === 200) {
             // Backend returns a formatted message
             let msg = "Upload Complete"
             
             if (result.message) {
                 msg = result.message
             } else if (result.matched !== undefined) {
                 msg = `Done! Linked: ${result.matched}, Skipped: ${result.skipped}`
             }
             
             pdfStatus.value = { 
                message: msg, 
                type: 'success',
                details: result.details || []
            }
            showToast(msg, 'success') // ✅ Show Toast
            
            selectedPdfs.value = [] // Clear selection
            if(pdfInput.value) pdfInput.value.value = ''
        } else {
            pdfStatus.value = { message: `❌ Error: ${result.message || 'Unknown Error'}`, type: 'error' }
            showToast("Upload Failed", 'error')
        }

    } catch (error: any) {
        console.error(error)
        const msg = error.response?.data?.message || 'Server Connection Failed'
        pdfStatus.value = { message: `❌ ${msg}`, type: 'error' }
        showToast(msg, 'error')
    } finally {
        isPdfUploading.value = false
    }
}
</script>

<template>
  <div>
    <!-- TOAST NOTIFICATION Removed (Global Toast used instead) -->

  <div class="w-full max-w-2xl mx-auto mt-10 p-4 sm:p-6 bg-white rounded-lg shadow-lg">
    <div class="text-center mb-8">
        <h2 class="text-2xl font-bold text-gray-800">Import Data</h2>
        <p class="text-gray-500">Upload a CSV file to bulk add research papers.</p>
    </div>

    <div class="border-2 border-dashed border-gray-300 rounded-xl p-10 flex flex-col items-center justify-center bg-gray-50 hover:bg-green-50 hover:border-green-400 transition relative">
        <div class="text-5xl mb-4">📂</div>
        
        <input 
            type="file" 
            ref="fileInput"
            accept=".csv"
            @change="handleFileChange"
            class="hidden"
            id="csvUpload"
        />
        
        <label for="csvUpload" class="cursor-pointer bg-green-600 text-white px-6 py-2 rounded-lg font-bold hover:bg-green-700 transition shadow-md">
            Select CSV File
        </label>
        
        <p v-if="!selectedFile" class="mt-3 text-sm text-gray-400">or drag and drop file here</p>
        <p v-else class="mt-3 text-lg font-medium text-green-700">📄 {{ selectedFile.name }}</p>
    </div>

    <div class="mt-6 bg-blue-50 p-5 rounded-lg border border-blue-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div class="text-sm text-blue-900">
            <p class="font-bold mb-1">ℹ️ Need the correct format?</p>
            <p class="text-blue-700">Download the template to match your catalog card.</p>
        </div>
        
        <button 
            @click="downloadTemplate" 
            class="bg-white border border-blue-300 text-blue-700 px-4 py-2 rounded font-bold hover:bg-blue-100 transition text-sm flex items-center gap-2 whitespace-nowrap shadow-sm"
        >
            📥 Download Template
        </button>
    </div>

    <div class="mt-8 flex justify-end gap-4">
        <div v-if="uploadStatus.message" :class="`flex-1 py-2 px-4 rounded font-bold flex items-center ${uploadStatus.type === 'error' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'}`">
            {{ uploadStatus.message }}
        </div>

        <button 
            @click="uploadCsv" 
            :disabled="!selectedFile || isUploading"
            class="bg-green-800 text-white px-6 py-3 rounded-lg font-bold hover:bg-green-900 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
        >
            <span v-if="isUploading">🔄 Processing...</span>
            <span v-else>🚀 Upload Data</span>
        </button>
    </div>
  </div>

  <!-- BULK PDF UPLOAD SECTION -->
  <div class="bg-white p-6 rounded-lg shadow-lg max-w-2xl mx-auto mt-10">
    <div class="text-center mb-8">
        <h2 class="text-2xl font-bold text-gray-800">Bulk Upload PDFs</h2>
        <p class="text-gray-500">Auto-link PDFs to researches by matching filenames to Titles.</p>
    </div>

    <div class="border-2 border-dashed border-gray-300 rounded-xl p-10 flex flex-col items-center justify-center bg-gray-50 hover:bg-blue-50 hover:border-blue-400 transition relative">
        <div class="text-5xl mb-4">📚</div>
        
        <input 
            type="file" 
            ref="pdfInput"
            accept=".pdf"
            multiple
            @change="handlePdfChange"
            class="hidden"
            id="pdfUpload"
        />
        
        <label for="pdfUpload" class="cursor-pointer bg-blue-600 text-white px-6 py-2 rounded-lg font-bold hover:bg-blue-700 transition shadow-md">
            Select PDF Files
        </label>
        
        <p v-if="!selectedPdfs.length" class="mt-3 text-sm text-gray-400">Select multiple PDF files (e.g. "My Title.pdf")</p>
        <p v-else class="mt-3 text-lg font-medium text-blue-700">📄 {{ selectedPdfs.length }} files selected</p>
    </div>

    <div class="mt-8 flex justify-end gap-4">
        <div v-if="pdfStatus.message" :class="`flex-1 py-2 px-4 rounded font-bold flex flex-col justify-center text-sm ${pdfStatus.type === 'error' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'}`">
            <span>{{ pdfStatus.message }}</span>
            <ul v-if="pdfStatus.details && pdfStatus.details.length" class="mt-1 text-xs list-disc border-t pt-1 border-opacity-20 border-black pl-4 max-h-32 overflow-y-auto">
                <li v-for="(detail, i) in pdfStatus.details" :key="i">{{ detail }}</li>
            </ul>
        </div>

        <button 
            @click="uploadPdfs" 
            :disabled="!selectedPdfs.length || isPdfUploading"
            class="bg-blue-800 text-white px-6 py-3 rounded-lg font-bold hover:bg-blue-900 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
        >
            <span v-if="isPdfUploading" class="flex items-center gap-2">
                <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Processing...
            </span>
            <span v-else>🔗 Link PDFs</span>
        </button>
    </div>
  </div>
  </div>
</template>