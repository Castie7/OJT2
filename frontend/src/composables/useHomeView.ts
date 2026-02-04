import { ref, onMounted, onUnmounted } from 'vue'
import { API_BASE_URL } from '../apiConfig' // ✅ Imported Central Configuration

// --- TYPE DEFINITIONS ---
export interface Research {
  id: number
  title: string
  abstract: string
  file_path?: string // ✅ Added for background image support
}

export interface Stat {
  id?: string // Add unique identifier to prevent key conflicts
  title: string
  value: string | number
  color: string
  action?: string 
}

export interface User {
  id: number
  name: string
  role: string
}

export function useHomeView() {
  
  // --- STATE ---
  const recentResearches = ref<Research[]>([])
  const currentSlide = ref(0)
  const slideInterval = ref<number | null>(null)

  // --- API ---
  const fetchSliderData = async () => {
    try {
      // ✅ Uses Centralized API_BASE_URL
      const response = await fetch(`${API_BASE_URL}/research`)
      
      if (response.ok) {
        const data = await response.json()
        // Take top 5 for the slider
        recentResearches.value = data.slice(0, 5)
      }
    } catch (e) {
      console.error("Failed to load slider data")
    }
  }

  // --- SLIDER LOGIC ---
  const nextSlide = () => {
    if (recentResearches.value.length === 0) return
    currentSlide.value = (currentSlide.value + 1) % recentResearches.value.length
  }

  const prevSlide = () => {
    if (recentResearches.value.length === 0) return
    currentSlide.value = (currentSlide.value - 1 + recentResearches.value.length) % recentResearches.value.length
  }

  const startSlideTimer = () => {
    stopSlideTimer()
    // Cast to unknown then number to satisfy TypeScript in browser env
    slideInterval.value = setInterval(nextSlide, 5000) as unknown as number 
  }

  const stopSlideTimer = () => {
    if (slideInterval.value !== null) {
      clearInterval(slideInterval.value)
      slideInterval.value = null
    }
  }

  // --- LIFECYCLE ---
  onMounted(() => {
    fetchSliderData()
    startSlideTimer()
  })

  onUnmounted(() => {
    stopSlideTimer()
  })

  return {
    recentResearches,
    currentSlide,
    nextSlide,
    prevSlide,
    startSlideTimer,
    stopSlideTimer
  }
}