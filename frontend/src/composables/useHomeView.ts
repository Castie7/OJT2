import { ref, onMounted, onUnmounted } from 'vue'

// --- TYPE DEFINITIONS ---
export interface Research {
  id: number
  title: string
  abstract: string
  // Add other fields if needed for the slider
}

export interface Stat {
  title: string
  value: string | number
  color: string
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
      const response = await fetch('http://localhost:8080/research')
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
    // Cast to any or number to satisfy TypeScript compiler for browser environments
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