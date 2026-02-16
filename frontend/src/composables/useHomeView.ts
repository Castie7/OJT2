import { ref, onMounted, onUnmounted } from 'vue'
import api from '../services/api' // ✅ Switch to your secure Axios instance
import type { User } from '../types'

// --- TYPE DEFINITIONS ---
export interface Stat {
  id?: string
  title: string
  value: number | string
  color: string
  action?: string
}

export interface Research {
  id: number
  title: string
  abstract: string
  file_path?: string
  crop_variation?: string
}

export function useHomeView() {

  // --- STATE ---
  const recentResearches = ref<Research[]>([])
  const currentSlide = ref(0)
  const slideInterval = ref<number | null>(null)

  // --- API ---
  const fetchSliderData = async () => {
    try {
      // ✅ Use api.get() instead of fetch()
      // No need for "API_BASE_URL" - the service handles it.
      const response = await api.get('/research')

      // Axios returns data directly in .data
      // Take top 5 for the slider
      recentResearches.value = response.data.slice(0, 5)

    } catch (e) {
      console.error("Failed to load slider data", e)
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