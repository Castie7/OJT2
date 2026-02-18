import { ref, onMounted, onUnmounted } from 'vue'
import { researchService } from '../services'
import type { Research } from '../types'

export function useHomeView() {

  // --- STATE ---
  const recentResearches = ref<Research[]>([])
  const currentSlide = ref(0)
  const slideInterval = ref<number | null>(null)

  // --- API ---
  const fetchSliderData = async () => {
    try {
      // Take top 5 for the slider
      const data = await researchService.getAll()
      recentResearches.value = data.slice(0, 5)

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