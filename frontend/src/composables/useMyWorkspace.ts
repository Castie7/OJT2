import { ref } from 'vue'

// Define Types
export interface UploadModalState {
  show: boolean
  title: string
  author: string
  crop_variation: string
  abstract: string
  start_date: string
  deadline_date: string
  file: File | null
}

export interface User {
  id: number
  name: string
  role: string
}

export function useMyWorkspace(currentUser: User | null) {
  
  // --- STATE ---
  const activeTab = ref<'submitted' | 'archived'>('submitted')
  const isUploading = ref(false)
  const uploadModal = ref<UploadModalState>({
    show: false,
    title: '',
    author: '',
    crop_variation: '',
    abstract: '',
    start_date: '',
    deadline_date: '',
    file: null
  })

  // --- HELPERS ---
  const getHeaders = () => {
    const token = document.cookie.split('; ').find(row => row.startsWith('auth_token='))?.split('=')[1]
    return { 'Authorization': token || '' }
  }

  // Get Today's date for "max" attributes
  const todayStr = new Date().toISOString().split('T')[0]

  // --- FILE HANDLING ---
  const handleUploadFile = (e: Event) => {
    const target = e.target as HTMLInputElement
    const file = target.files?.[0]

    // 1. Reset if cancelled
    if (!file) {
      uploadModal.value.file = null
      return
    }

    // 2. Validate Extension
    const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png']
    const fileExtension = file.name.split('.').pop()?.toLowerCase() || ''

    if (!allowedExtensions.includes(fileExtension)) {
      alert("❌ Invalid File!\nPlease upload a PDF or an Image (JPG/PNG).")
      target.value = '' // Clear visual input
      uploadModal.value.file = null
      return
    }

    // Valid
    uploadModal.value.file = file
  }

  // --- SUBMISSION LOGIC ---
  const submitResearch = async (refreshCallback?: () => void) => {
    const form = uploadModal.value

    // 1. Basic Validation
    if (!form.title.trim()) { alert("⚠️ Title is required."); return }
    if (!form.author.trim()) { alert("⚠️ Author is required."); return }
    if (!form.deadline_date) { alert("⚠️ Deadline Date is required."); return }
    if (!form.file) { alert("⚠️ File is missing.\nPlease select a valid PDF or Image."); return }

    // 2. Date Validation
    const start = form.start_date ? new Date(form.start_date) : null
    const deadline = new Date(form.deadline_date)
    const minYear = 2000
    const maxYear = 2100

    if (deadline.getFullYear() < minYear || deadline.getFullYear() > maxYear) {
      alert(`⚠️ Invalid Deadline Date.\nPlease enter a year between ${minYear} and ${maxYear}.`)
      return
    }

    if (start) {
      if (start.getFullYear() < minYear || start.getFullYear() > maxYear) {
        alert(`⚠️ Invalid Start Date.\nPlease enter a year between ${minYear} and ${maxYear}.`)
        return
      }
      if (deadline < start) {
        alert("⚠️ Date Error: Deadline cannot be before the Start Date.")
        return
      }
    }

    // 3. Prepare Upload
    isUploading.value = true
    const formData = new FormData()
    formData.append('title', form.title)
    formData.append('author', form.author)
    formData.append('crop_variation', form.crop_variation)
    formData.append('abstract', form.abstract)
    formData.append('start_date', form.start_date)
    formData.append('deadline_date', form.deadline_date)
    if (currentUser) formData.append('uploaded_by', String(currentUser.id))
    if (form.file) formData.append('pdf_file', form.file)

    try {
      const res = await fetch('http://localhost:8080/research/create', {
        method: 'POST',
        headers: getHeaders(), // Note: Headers for FormData usually shouldn't set Content-Type manually
        body: formData
      })
      
      const result = await res.json()

      if (res.ok) {
        alert("✅ Success! Research Submitted.")
        // Reset Form
        uploadModal.value = { 
          show: false, title: '', author: '', crop_variation: '', abstract: '', 
          start_date: '', deadline_date: '', file: null 
        }
        // Trigger Refresh in Component
        if (refreshCallback) refreshCallback()
      } else {
        if (result.messages) {
          const msg = typeof result.messages === 'object' 
            ? Object.values(result.messages).join('\n') 
            : result.messages
          alert("❌ Submission Failed:\n" + msg)
        } else {
          alert("❌ Error: " + (result.message || "Upload Failed"))
        }
      }
    } catch (error) {
      console.error(error)
      alert("❌ Server Error.")
    } finally {
      isUploading.value = false
    }
  }

  return {
    activeTab,
    uploadModal,
    isUploading,
    todayStr,
    handleUploadFile,
    submitResearch
  }
}