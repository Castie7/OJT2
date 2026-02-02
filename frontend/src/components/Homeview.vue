<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useHomeView, type User, type Stat } from '../composables/useHomeView'

const props = defineProps<{
  currentUser: User | null
  stats: Stat[] // Initial stats from parent
}>()

const emit = defineEmits<{
  (e: 'browse-click'): void
  (e: 'stat-click', tab: string): void
}>()

const { 
  recentResearches, currentSlide, nextSlide, prevSlide, 
  startSlideTimer, stopSlideTimer 
} = useHomeView()

// --- FIX: Live Stats Logic ---
// We create a local copy so we can overwrite it with fresh API data
const displayStats = ref<Stat[]>([...props.stats])

const fetchLiveAdminStats = async () => {
  // Only fetch if user is Admin
  if (props.currentUser?.role !== 'admin') return

  try {
    // Add timestamp to prevent browser caching
    const res = await fetch(`http://localhost:8080/research/stats?t=${Date.now()}`)
    if (res.ok) {
      const data = await res.json()
      
      // Update the stats with live numbers
      displayStats.value = [
        { 
          title: 'Total Published', 
          value: data.total, 
          color: 'text-green-600', 
          action: 'submitted' // or whatever tab name you use for the library
        },
        { 
          title: 'Pending Reviews', 
          value: data.pending, // <--- THIS IS THE FIX
          color: 'text-amber-600', 
          action: 'pending' 
        },
        { 
          title: 'Registered Users', 
          value: data.users || 0, 
          color: 'text-blue-600', 
          action: 'users' // Optional: if you have a users tab
        }
      ]
    }
  } catch (error) {
    console.error("Error fetching live stats:", error)
  }
}

// Fetch on mount
onMounted(() => {
  fetchLiveAdminStats()
})

// Keep local stats in sync if props change (for non-admins)
watch(() => props.stats, (newStats) => {
  if (props.currentUser?.role !== 'admin') {
    displayStats.value = newStats
  }
})
</script>

<template>
  <div class="space-y-8"> 
    
    

    <div v-if="currentUser">
      <h1 class="text-2xl font-bold text-gray-900 mb-4">
        {{ currentUser.role === 'admin' ? '📢 System Overview (Admin)' : '👋 My Research Overview' }}
      </h1>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div 
            v-for="stat in displayStats" 
            :key="stat.title" 
            @click="stat.action ? emit('stat-click', stat.action) : null"
            :class="[
              'bg-white p-6 rounded-lg shadow border-l-4 transition-transform duration-200',
              stat.color === 'text-red-600' ? 'border-red-500' : 
              stat.color === 'text-orange-500' ? 'border-orange-500' : 
              stat.color === 'text-amber-600' ? 'border-amber-500' : 
              stat.color === 'text-blue-600' ? 'border-blue-500' : 'border-green-500',
              stat.action ? 'cursor-pointer hover:scale-105 hover:shadow-lg' : '' 
            ]"
        >
          <div class="flex justify-between items-start">
            <div>
              <h3 class="text-gray-500 text-sm uppercase font-semibold">{{ stat.title }}</h3>
              <p :class="`text-4xl font-bold mt-2 ${stat.color}`">{{ stat.value }}</p>
            </div>
            <span v-if="stat.action" class="text-gray-300 text-xl">↗</span>
          </div>
          <div v-if="stat.action" class="mt-2 text-xs text-gray-400 font-medium">
             {{ stat.action === 'workspace' ? 'Go to My Workspace' : 'Click to view' }}
          </div>
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
        class="absolute inset-0 flex slide-transition" 
        :style="{ transform: `translateX(-${currentSlide * 100}%)` }"
      >
        <div 
          v-for="item in recentResearches" 
          :key="item.id" 
          class="min-w-full h-full relative"
        >
           <div class="absolute inset-0 bg-gradient-to-r from-black via-transparent to-transparent z-10"></div>
           <div class="absolute inset-0 bg-green-900 opacity-30"></div> 
           <div class="absolute inset-0 opacity-10 bg-pattern-cubes"></div>

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
               class="px-6 py-3 bg-white text-green-900 font-bold rounded hover:bg-yellow-400 flex items-center gap-2 btn-hover-effect"
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
        <button @click="$emit('browse-click')" class="bg-green-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-green-700 shadow-lg hover:shadow-xl btn-hover-effect">
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

<style scoped src="../assets/styles/HomeView.css"></style>