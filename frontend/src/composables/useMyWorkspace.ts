import { ref, reactive, watch } from 'vue'
import api from '../services/api' // ✅ Switch to Secure API Service

export interface User {
  id: number
  name: string
  role: string
  email: string
}

export interface Research {
  id: number
  title: string
  author: string
  status: string
  crop_variation: string
  start_date: string
  deadline_date: string
  knowledge_type: string
  publication_date: string
  edition: string
  publisher: string
  physical_description: string
  isbn_issn: string
  subjects: string
  shelf_location: string
  item_condition: string
  link: string
  file_path?: string
  rejected_at?: string
  archived_at?: string
}

export function useMyWorkspace(currentUser: User | null) {
  
  const activeTab = ref<'submitted' | 'archived'>('submitted')
  const isModalOpen = ref(false)
  const isSubmitting = ref(false)
  const isLoading = ref(false) 
  const myResearches = ref<Research[]>([])

  // FORM STATE
  const form = reactive({
    id: null as number | null,
    title: '',
    author: '',
    crop_variation: '',
    start_date: '',
    deadline_date: '',
    knowledge_type: 'Research Paper', 
    publication_date: '',
    edition: '',
    publisher: '',
    physical_description: '',
    isbn_issn: '',
    subjects: '',
    shelf_location: '',
    item_condition: 'Good', 
    link: '',
    pdf_file: null as File | null
  })

  // --- ACTIONS ---

  // 1. FETCH DATA
  const fetchMyResearches = async () => {
    isLoading.value = true
    try {
        // ✅ Use api.get()
        const endpoint = activeTab.value === 'archived' 
            ? '/research/my-archived'
            : '/research/my-submissions';

        const response = await api.get(endpoint);
        myResearches.value = response.data;
        
    } catch (e) {
        console.error("Failed to fetch data", e);
    } finally {
        isLoading.value = false;
    }
  }

  // Watch for tab changes to reload data
  watch(activeTab, () => {
      fetchMyResearches();
  });

  // 2. OPEN FOR NEW SUBMISSION
  const openSubmitModal = () => {
    Object.assign(form, {
      id: null, 
      title: '', author: '', crop_variation: '', 
      start_date: '', deadline_date: '',
      knowledge_type: 'Research Paper',
      publication_date: '', edition: '', publisher: '',
      physical_description: '', isbn_issn: '', subjects: '',
      shelf_location: '', item_condition: 'Good', link: '',
      pdf_file: null
    })
    isModalOpen.value = true
  }

  // 3. OPEN FOR EDITING
  const openEditModal = (item: Research) => {
    Object.assign(form, {
      id: item.id, 
      title: item.title,
      author: item.author,
      crop_variation: item.crop_variation || '',
      start_date: item.start_date || '',
      deadline_date: item.deadline_date || '',
      knowledge_type: item.knowledge_type || 'Research Paper',
      publication_date: item.publication_date || '',
      edition: item.edition || '',
      publisher: item.publisher || '',
      physical_description: item.physical_description || '',
      isbn_issn: item.isbn_issn || '',
      subjects: item.subjects || '',
      shelf_location: item.shelf_location || '',
      item_condition: item.item_condition || 'Good',
      link: item.link || '',
      pdf_file: null 
    })
    isModalOpen.value = true
  }

  const handleFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement
    const file = target.files?.[0]
    if (!file) { form.pdf_file = null; return }

    const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png']
    const fileExtension = file.name.split('.').pop()?.toLowerCase() || ''

    if (!allowedExtensions.includes(fileExtension)) {
      alert("❌ Invalid File!\nPlease upload a PDF or an Image.")
      target.value = '' 
      form.pdf_file = null
      return
    }
    form.pdf_file = file
  }

  const submitResearch = async (): Promise<boolean> => {
    if (!form.title.trim()) { alert("⚠️ Title is required."); return false }
    if (!form.author.trim()) { alert("⚠️ Author is required."); return false }

    isSubmitting.value = true
    const formData = new FormData()

    // Append Fields
    formData.append('title', form.title)
    formData.append('author', form.author)
    formData.append('crop_variation', form.crop_variation)
    formData.append('start_date', form.start_date)
    formData.append('deadline_date', form.deadline_date)
    formData.append('knowledge_type', form.knowledge_type)
    formData.append('publication_date', form.publication_date)
    formData.append('edition', form.edition)
    formData.append('publisher', form.publisher)
    formData.append('physical_description', form.physical_description)
    formData.append('isbn_issn', form.isbn_issn)
    formData.append('subjects', form.subjects)
    formData.append('shelf_location', form.shelf_location)
    formData.append('item_condition', form.item_condition)
    formData.append('link', form.link)
    
    if (form.pdf_file) formData.append('pdf_file', form.pdf_file)

    try {
      // ✅ Use api.post()
      const url = form.id 
        ? `/research/update/${form.id}`
        : `/research/create`

      await api.post(url, formData)
      
      alert(form.id ? "✅ Success! Research Updated." : "✅ Success! Research Submitted.")
      isModalOpen.value = false
      return true // Indicate success

    } catch (error: any) {
      console.error(error)
      
      // ✅ Improved Error Handling
      let msg = "Action Failed";
      if (error.response?.data?.messages) {
          msg = Object.values(error.response.data.messages).join('\n');
      } else if (error.response?.data?.message) {
          msg = error.response.data.message;
      }

      alert("❌ Error:\n" + msg)
      return false // Indicate failure
    } finally {
      isSubmitting.value = false
    }
  }

  return {
    activeTab,
    myResearches,
    isLoading, 
    isModalOpen,
    isSubmitting,
    form,
    fetchMyResearches, 
    openSubmitModal,
    openEditModal, 
    submitResearch,
    handleFileChange
  }
}