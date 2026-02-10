<script setup lang="ts">
import { ref } from 'vue'
import api from '../services/api' // ✅ Switch to Secure API Service

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
</script>

<template>
  <div class="bg-white p-6 rounded-lg shadow-lg max-w-2xl mx-auto mt-10">
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
</template>