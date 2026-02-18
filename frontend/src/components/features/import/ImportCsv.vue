<script setup lang="ts">
import { ref } from 'vue'
import api from '../../../services/api' // ✅ Switch to Secure API Service
import { apiCache } from '../../../utils/apiCache'
import { useToast } from '../../../composables/useToast'
import BaseCard from '../../ui/BaseCard.vue'
import BaseButton from '../../ui/BaseButton.vue'
import Papa from 'papaparse'

const emit = defineEmits<{
  (e: 'upload-success'): void
}>()

const fileInput = ref<HTMLInputElement | null>(null)
const selectedFile = ref<File | null>(null)
const isUploading = ref(false)
const uploadStatus = ref<{ message: string, type: 'success' | 'error' | '' }>({ message: '', type: '' })

// 1. Handle File Selection
const handleFileChange = (event: Event | DragEvent) => {
  let file: File | undefined;

  // Handle Drop Event
  if (event instanceof DragEvent && event.dataTransfer) {
      if (event.dataTransfer.files && event.dataTransfer.files[0]) {
          file = event.dataTransfer.files[0];
      }
  } 
  // Handle Input Change Event
  else {
      const target = event.target as HTMLInputElement;
      if (target.files && target.files[0]) {
          file = target.files[0];
      }
  }

  if (file) {
    // Validate .csv extension
    if (file.type !== 'text/csv' && !file.name.endsWith('.csv')) {
        uploadStatus.value = { message: '❌ Please select a valid .csv file', type: 'error' }
        selectedFile.value = null
        if (event.target instanceof HTMLInputElement) event.target.value = '' 
        return
    }

    selectedFile.value = file
    uploadStatus.value = { message: '', type: '' }
  }
}

// 2. Download Template Logic
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
const uploadCsv = () => {
    if (!selectedFile.value) return

    isUploading.value = true
    uploadStatus.value = { message: '⏳ Parsing CSV...', type: '' }

    Papa.parse(selectedFile.value, {
        header: true,
        skipEmptyLines: true,
        complete: async (results) => {
            const rows = results.data as any[]
            const total = rows.length
            let success = 0
            let skipped = 0
            let errors = 0
            
            if (total === 0) {
                uploadStatus.value = { message: '❌ CSV is empty', type: 'error' }
                isUploading.value = false
                return
            }

            for (let i = 0; i < total; i++) {
                const row = rows[i]
                uploadStatus.value = { message: `⏳ Importing ${i + 1}/${total}...`, type: '' }

                try {
                    const response = await api.post('/research/import-single', row)
                    
                    if (response.data.status === 'success') {
                        success++
                    } else if (response.data.status === 'skipped') {
                        skipped++
                    } else {
                        errors++
                    }
                } catch (error) {
                    console.error("Row import failed", row, error)
                    errors++
                }
            }

            isUploading.value = false
            uploadStatus.value = { 
                message: `✅ Completed! Imported: ${success}, Skipped (Dup): ${skipped}, Errors: ${errors}.`, 
                type: 'success' 
            }
            
            selectedFile.value = null
            if(fileInput.value) fileInput.value.value = ''
            apiCache.invalidate('research') // clear stale cache so UI shows new data
            emit('upload-success')
        },
        error: (error) => {
            console.error(error)
            uploadStatus.value = { message: `❌ CSV Parse Error: ${error.message}`, type: 'error' }
            isUploading.value = false
        }
    })
}

// 4. Bulk PDF Upload Logic
const pdfInput = ref<HTMLInputElement | null>(null)
const selectedPdfs = ref<File[]>([])
const isPdfUploading = ref(false)
const pdfStatus = ref<{ message: string, type: 'success' | 'error' | '', details?: string[] }>({ message: '', type: '', details: [] })

// Toast State
const { showToast } = useToast()

const handlePdfChange = (event: Event | DragEvent) => {
    let files: FileList | null = null;

    if (event instanceof DragEvent && event.dataTransfer) {
        files = event.dataTransfer.files;
    } else {
        const target = event.target as HTMLInputElement;
        files = target.files;
    }

    if (files && files.length) {
        if (files.length > 10) {
            showToast("You can only upload a maximum of 10 files at a time.", "warning")
            if (event.target instanceof HTMLInputElement) event.target.value = '' // Reset input
            selectedPdfs.value = []
            return
        }
        // Convert FileList to Array
        selectedPdfs.value = Array.from(files)
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
            apiCache.invalidate('research') // clear stale cache
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
  <div class="space-y-8 animate-fade-in">
    
    <!-- Header -->
    <div class="flex items-center justify-between">
         <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
            <span class="text-3xl">📂</span> Data Management
         </h1>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <!-- CSV UPLOAD CARD -->
        <BaseCard class="space-y-6">
            <div class="border-b border-gray-100 pb-4">
                <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <span class="bg-emerald-100 text-emerald-600 p-1.5 rounded-lg text-lg">📊</span>
                    Import CSV Data
                </h2>
                <p class="text-sm text-gray-500 mt-1">Bulk upload research records using a CSV file.</p>
            </div>

            <!-- Dropzone -->
            <div 
                class="border-2 border-dashed border-gray-200 rounded-xl p-8 flex flex-col items-center justify-center bg-gray-50/50 hover:bg-emerald-50/50 hover:border-emerald-300 transition-all group relative cursor-pointer"
                @click="fileInput?.click()"
                @dragover.prevent
                @drop.prevent="handleFileChange"
            >
                <div class="w-16 h-16 bg-white rounded-full shadow-sm flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                    <span class="text-3xl">📄</span>
                </div>
                
                <input 
                    type="file" 
                    ref="fileInput"
                    accept=".csv"
                    @change="handleFileChange"
                    class="hidden"
                    id="csvUpload"
                />
                
                <div v-if="!selectedFile">
                    <p class="font-bold text-gray-700 text-center">Click to upload or drag and drop</p>
                    <p class="text-xs text-gray-400 text-center mt-1">CSV files only (max 5MB)</p>
                </div>
                <div v-else class="text-center">
                    <p class="font-bold text-emerald-700 break-all">{{ selectedFile.name }}</p>
                    <p class="text-xs text-emerald-500 mt-1">Ready to upload</p>
                    <button @click.stop="selectedFile = null; if(fileInput) fileInput.value = ''" class="mt-2 text-xs text-red-400 hover:text-red-600 font-bold hover:underline">Remove</button>
                </div>
            </div>

            <!-- Template Download -->
            <div class="bg-blue-50 px-4 py-3 rounded-lg border border-blue-100 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="text-blue-500">ℹ️</span>
                    <span class="text-xs text-blue-800 font-medium">Need the correct format?</span>
                </div>
                <button 
                    @click="downloadTemplate" 
                    class="text-xs font-bold text-blue-600 hover:text-blue-800 hover:underline whitespace-nowrap"
                >
                    Download Template
                </button>
            </div>

            <!-- Actions -->
            <div class="flex flex-col gap-3">
                 <div v-if="uploadStatus.message" :class="`text-xs font-bold p-3 rounded-lg flex items-center gap-2 ${uploadStatus.type === 'error' ? 'bg-red-50 text-red-600 border border-red-100' : 'bg-green-50 text-green-700 border border-green-100'}`">
                    <span>{{ uploadStatus.type === 'error' ? '❌' : '✅' }}</span>
                    {{ uploadStatus.message }}
                 </div>

                 <BaseButton 
                    @click="uploadCsv" 
                    :disabled="!selectedFile || isUploading"
                    variant="primary"
                    class="w-full justify-center"
                >
                    <span v-if="isUploading" class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full mr-2"></span>
                    {{ isUploading ? 'Processing...' : 'Upload CSV Data' }}
                 </BaseButton>
            </div>
        </BaseCard>

        <!-- PDF BULK UPLOAD CARD -->
        <BaseCard class="space-y-6">
            <div class="border-b border-gray-100 pb-4">
                <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <span class="bg-blue-100 text-blue-600 p-1.5 rounded-lg text-lg">📎</span>
                    Bulk PDF Upload
                </h2>
                <p class="text-sm text-gray-500 mt-1">Auto-link PDFs to existing records by filename.</p>
            </div>

            <!-- Dropzone -->
            <div 
                class="border-2 border-dashed border-gray-200 rounded-xl p-8 flex flex-col items-center justify-center bg-gray-50/50 hover:bg-blue-50/50 hover:border-blue-300 transition-all group relative cursor-pointer"
                @click="pdfInput?.click()"
                @dragover.prevent
                @drop.prevent="handlePdfChange"
            >
                <div class="w-16 h-16 bg-white rounded-full shadow-sm flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                    <span class="text-3xl">📚</span>
                </div>
                
                <input 
                    type="file" 
                    ref="pdfInput"
                    accept=".pdf"
                    multiple
                    @change="handlePdfChange"
                    class="hidden"
                    id="pdfUpload"
                />
                
                <div v-if="!selectedPdfs.length">
                    <p class="font-bold text-gray-700 text-center">Click to upload or drag and drop</p>
                    <p class="text-xs text-gray-400 text-center mt-1">Multiple PDFs allowed (Max 10)</p>
                </div>
                <div v-else class="text-center w-full">
                    <p class="font-bold text-blue-700">{{ selectedPdfs.length }} files selected</p>
                    <div class="mt-2 max-h-20 overflow-y-auto text-xs text-gray-500 space-y-1 custom-scrollbar px-4">
                        <div v-for="file in selectedPdfs" :key="file.name" class="truncate">{{ file.name }}</div>
                    </div>
                    <button @click.stop="selectedPdfs = []; if(pdfInput) pdfInput.value = ''" class="mt-3 text-xs text-red-400 hover:text-red-600 font-bold hover:underline">Clear All</button>
                </div>
            </div>

            <div class="bg-gray-50 px-4 py-3 rounded-lg border border-gray-100">
                <p class="text-xs text-gray-500 leading-relaxed">
                    <strong>Note:</strong> Make sure the PDF filenames exactly match the <em>Title</em> of the record in the database for automatic linking.
                </p>
            </div>

            <!-- Actions -->
             <div class="flex flex-col gap-3">
                 <div v-if="pdfStatus.message" :class="`text-xs font-bold p-3 rounded-lg ${pdfStatus.type === 'error' ? 'bg-red-50 text-red-600 border border-red-100' : 'bg-green-50 text-green-700 border border-green-100'}`">
                    <div class="flex items-center gap-2 mb-1">
                        <span>{{ pdfStatus.type === 'error' ? '❌' : '✅' }}</span>
                        <span>{{ pdfStatus.message }}</span>
                    </div>
                    <ul v-if="pdfStatus.details && pdfStatus.details.length" class="mt-2 pl-4 list-disc text-[10px] max-h-24 overflow-y-auto custom-scrollbar opacity-80">
                        <li v-for="(detail, i) in pdfStatus.details" :key="i">{{ detail }}</li>
                    </ul>
                 </div>

                 <BaseButton 
                    @click="uploadPdfs" 
                    :disabled="!selectedPdfs.length || isPdfUploading"
                    variant="secondary"
                    class="w-full justify-center"
                >
                    <span v-if="isPdfUploading" class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full mr-2"></span>
                    {{ isPdfUploading ? 'Linking Files...' : 'Upload & Link PDFs' }}
                 </BaseButton>
            </div>

        </BaseCard>
    </div>
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

/* Custom Scrollbar */
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
</style>
```