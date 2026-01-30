<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'

defineProps(['currentUser', 'stats'])
const emit = defineEmits(['browse-click']) 

// --- SLIDER STATE ---
const recentResearches = ref([])
const currentSlide = ref(0)
const slideInterval = ref(null)

// --- FETCH PUBLIC DATA (For Slider) ---
const fetchSliderData = async () => {
  try {
    // Fetch public approved list
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
  if (recentResearches.value.length === 0) return;
  currentSlide.value = (currentSlide.value + 1) % recentResearches.value.length;
}

const prevSlide = () => {
  if (recentResearches.value.length === 0) return;
  currentSlide.value = (currentSlide.value - 1 + recentResearches.value.length) % recentResearches.value.length;
}

const startSlideTimer = () => {
  stopSlideTimer();
  slideInterval.value = setInterval(nextSlide, 5000); // 5 Seconds
}

const stopSlideTimer = () => {
  if (slideInterval.value) clearInterval(slideInterval.value);
}

// --- LIFECYCLE ---
onMounted(() => {
  fetchSliderData();
  startSlideTimer();
})

onUnmounted(() => {
  stopSlideTimer();
})
</script>

<template>
  <div class="space-y-8"> 
    
    <div v-if="currentUser">
      <h1 class="text-2xl font-bold text-gray-900 mb-4">📢 System Overview (Admin Only)</h1>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div v-for="stat in stats" :key="stat.title" class="bg-white p-6 rounded-lg shadow border-l-4 border-green-500">
          <h3 class="text-gray-500 text-sm uppercase font-semibold">{{ stat.title }}</h3>
          <p :class="`text-4xl font-bold mt-2 ${stat.color}`">{{ stat.value }}</p>
        </div>
      </div>
    </div>

    <div 
      v-if="recentResearches.length > 0" 
      class="relative w-full h-[400px] rounded-2xl overflow-hidden shadow-2xl group bg-gray-900"
      @mouseenter="stopSlideTimer"
      @mouseleave="startSlideTimer"
    >
      <div 
        class="absolute inset-0 flex transition-transform duration-700 ease-in-out" 
        :style="{ transform: `translateX(-${currentSlide * 100}%)` }"
      >
        <div 
          v-for="(item, index) in recentResearches" 
          :key="item.id" 
          class="min-w-full h-full relative"
        >
           <div class="absolute inset-0 bg-gradient-to-r from-black via-transparent to-transparent z-10"></div>
           <div class="absolute inset-0 bg-green-900 opacity-30"></div> 
           
           <div class="absolute inset-0 opacity-10 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')]"></div>

           <div class="absolute bottom-0 left-0 p-8 md:p-12 z-20 max-w-3xl">
              <span class="inline-block px-3 py-1 bg-yellow-500 text-green-900 text-xs font-bold rounded mb-3">
                Featured Study
              </span>
              <h2 class="text-3xl md:text-5xl font-bold text-white mb-4 leading-tight drop-shadow-lg">
                {{ item.title }}
              </h2>
              <p class="text-gray-200 text-sm md:text-base line-clamp-2 mb-6 border-l-4 border-yellow-500 pl-4">
                {{ item.abstract || 'Explore this latest research in our library.' }}
              </p>
              
              <button 
                @click="$emit('browse-click')" 
                class="px-6 py-3 bg-white text-green-900 font-bold rounded hover:bg-yellow-400 transition flex items-center gap-2 transform hover:scale-105 active:scale-95"
              >
                <span>📖</span> Read Full Paper
              </button>
           </div>
        </div>
      </div>

      <button @click="prevSlide" class="absolute left-4 top-1/2 -translate-y-1/2 bg-black/50 text-white p-3 rounded-full hover:bg-green-600 transition opacity-0 group-hover:opacity-100 z-30 backdrop-blur-sm">❮</button>
      <button @click="nextSlide" class="absolute right-4 top-1/2 -translate-y-1/2 bg-black/50 text-white p-3 rounded-full hover:bg-green-600 transition opacity-0 group-hover:opacity-100 z-30 backdrop-blur-sm">❯</button>

      <div class="absolute bottom-6 right-8 flex gap-2 z-30">
        <button 
          v-for="(item, index) in recentResearches" 
          :key="index"
          @click="currentSlide = index"
          :class="`w-2 h-2 rounded-full transition-all duration-300 ${currentSlide === index ? 'w-8 bg-yellow-400' : 'bg-gray-500 hover:bg-gray-300'}`"
        ></button>
      </div>
    </div>

    <div class="bg-white p-8 rounded-lg shadow-lg border border-gray-200 relative overflow-hidden">
      <div class="absolute top-0 right-0 -mt-4 -mr-4 w-32 h-32 bg-yellow-400 rounded-full opacity-20 blur-xl"></div>
      
      <h1 class="text-4xl font-bold text-green-800 mb-4 relative z-10">Welcome to BSU RootCrops</h1>
      <p class="text-lg text-gray-600 mb-6 relative z-10">The official repository for root crop research. Browse our open collection of research data.</p>
      <div class="flex gap-4 relative z-10">
        <button @click="$emit('browse-click')" class="bg-green-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-green-700 transition shadow-lg hover:shadow-xl transform hover:-translate-y-1">
          Browse All Researches
        </button>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
      
      <div class="bg-green-900 text-white p-8 rounded-xl shadow-lg relative overflow-hidden group hover:shadow-2xl transition duration-300">
        <div class="absolute top-0 right-0 w-24 h-24 bg-white opacity-5 rounded-bl-full transition group-hover:scale-110 duration-500"></div>
        <div class="flex items-center gap-3 mb-4">
          <span class="text-3xl">👁️</span>
          <h2 class="text-2xl font-bold tracking-wide">VISION</h2>
        </div>
        <p class="text-green-100 leading-relaxed text-lg">
          A premier center for root crop research and development, advancing sustainable agriculture and food security in the Cordillera region and beyond.
        </p>
      </div>

      <div class="bg-yellow-500 text-green-900 p-8 rounded-xl shadow-lg relative overflow-hidden group hover:shadow-2xl transition duration-300">
        <div class="absolute top-0 right-0 w-24 h-24 bg-white opacity-20 rounded-bl-full transition group-hover:scale-110 duration-500"></div>
        <div class="flex items-center gap-3 mb-4">
          <span class="text-3xl">🎯</span>
          <h2 class="text-2xl font-bold tracking-wide">MISSION</h2>
        </div>
        <p class="text-green-900 leading-relaxed text-lg font-medium">
          To generate relevant technologies and information on root crops through rigorous research, preserving genetic diversity and empowering local farming communities.
        </p>
      </div>

    </div>

  </div>
</template>