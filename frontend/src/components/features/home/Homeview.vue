 <script setup lang="ts">
import { useHomeView } from '../../../composables/useHomeView'
import type { Stat } from '../../../types'
import { useAuthStore } from '../../../stores/auth'
import BaseButton from '../../ui/BaseButton.vue'
import BaseCard from '../../ui/BaseCard.vue'

defineProps<{
  stats: Stat[] // title, value, color
}>()

const authStore = useAuthStore()

const emit = defineEmits<{
  (e: 'browse-click'): void
  (e: 'stat-click', tab: string): void
}>()

const { 
  recentResearches, currentSlide, 
  startSlideTimer, stopSlideTimer 
} = useHomeView()

// Map crop variation to a local background image
const getCropImage = (crop?: string): string => {
  const c = (crop || '').toLowerCase()
  if (c.includes('sweetpotato') || c.includes('sweet potato') || c.includes('kamote')) {
    return '/images/crops/sweetpotato.jpg'
  }
  if (c.includes('potato')) {
    return '/images/crops/potato.jpg'
  }
  if (c.includes('cassava') || c.includes('kamoteng kahoy')) {
    return '/images/crops/cassava.jpg'
  }
  if (c.includes('yam') || c.includes('ubi')) {
    return '/images/crops/yam.jpg'
  }
  if (c.includes('taro') || c.includes('gabi')) {
    return '/images/crops/taro.jpg'
  }
  // Default: generic agriculture field
  return '/images/crops/default.jpg'
}
</script>

<template>
  <div class="space-y-8 animate-fade-in"> 
    
    <div v-if="authStore.currentUser">
      <h1 class="text-2xl font-bold text-gray-900 mb-6">
        {{ authStore.currentUser.role === 'admin' ? '📢 System Overview' : '👋 My Research Overview' }}
      </h1>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <BaseCard 
            v-for="stat in stats" 
            :key="stat.id || stat.title" 
            @click="stat.action ? emit('stat-click', stat.action) : null"
            class="transition-all duration-200 hover:-translate-y-1"
            :class="stat.action ? 'cursor-pointer' : ''"
        >
          <div class="flex items-center gap-4">
            <div 
                class="w-12 h-12 rounded-full flex items-center justify-center text-xl"
                :class="[
                    stat.color === 'text-red-600' ? 'bg-red-50 text-red-600' :
                    stat.color === 'text-orange-500' ? 'bg-orange-50 text-orange-600' :
                    stat.color === 'text-teal-600' ? 'bg-teal-50 text-teal-600' : 'bg-emerald-50 text-emerald-600'
                ]"
            >
                {{ stat.title.includes('Total') ? '📊' : stat.title.includes('Pending') ? '⏳' : '✅' }}
            </div>
            <div>
              <p class="text-gray-500 text-xs uppercase font-bold tracking-wider">{{ stat.title }}</p>
              <h3 class="text-3xl font-bold text-gray-900">{{ stat.value }}</h3>
            </div>
          </div>
        </BaseCard>
      </div>
    </div>

    <!-- Featured Research Carousel -->
    <div 
      v-if="recentResearches.length > 0" 
      class="relative w-full h-[400px] rounded-2xl overflow-hidden shadow-lg group bg-gray-900"
      @mouseenter="stopSlideTimer"
      @mouseleave="startSlideTimer"
    >
      <div 
        class="absolute inset-0 flex transition-transform duration-700 ease-out" 
        :style="{ transform: `translateX(-${currentSlide * 100}%)` }"
      >
        <div 
          v-for="item in recentResearches" 
          :key="item.id" 
          class="min-w-full h-full relative"
        >
           <img 
             :src="getCropImage(item.crop_variation)" 
             :alt="item.crop_variation || 'Root Crops'"
             class="absolute inset-0 w-full h-full object-cover opacity-60"
           />
           <div class="absolute inset-0 bg-gradient-to-t from-gray-900 via-gray-900/40 to-transparent"></div>

           <div class="absolute bottom-0 left-0 p-8 md:p-12 max-w-4xl">
              <div class="flex items-center gap-2 mb-3">
                <span class="px-2 py-0.5 bg-emerald-500 text-white text-[10px] uppercase font-bold rounded">Featured</span>
                <span v-if="item.crop_variation" class="text-emerald-300 text-sm font-medium">
                  {{ item.crop_variation }}
                </span>
              </div>
              <h2 class="text-3xl md:text-4xl font-bold text-white mb-2 leading-tight">
                {{ item.title }}
              </h2>
              <p class="text-gray-300 text-sm line-clamp-2 mb-6 max-w-xl">
                {{ item.abstract || 'Explore this latest research in our library.' }}
              </p>
              
              <BaseButton 
                @click="$emit('browse-click')" 
                size="md"
                class="!bg-white !text-gray-900 hover:!bg-emerald-50 font-semibold border-none"
              >
                Read Paper
              </BaseButton>
           </div>
        </div>
      </div>

      <!-- Carousel Controls -->
      <div class="absolute bottom-6 right-6 flex gap-2">
        <button 
          v-for="(_, index) in recentResearches" 
          :key="index"
          @click="currentSlide = index"
          :class="`h-1.5 rounded-full transition-all duration-300 ${currentSlide === index ? 'w-6 bg-emerald-500' : 'w-1.5 bg-white/30 hover:bg-white'}`"
        ></button>
      </div>
    </div>

    <!-- Welcome Section -->
    <BaseCard class="bg-gradient-to-r from-emerald-50 to-white border-none !p-8">
      <div class="flex flex-col md:flex-row items-center justify-between gap-6">
        <div>
            <h1 class="text-2xl font-bold text-emerald-900 mb-2">Welcome to BSU RootCrops</h1>
            <p class="text-gray-600 max-w-2xl text-sm">The official repository for root crop research. Browse our open collection of research data and gain insights into agricultural innovation.</p>
        </div>
        <div class="flex gap-3 shrink-0">
          <BaseButton @click="$emit('browse-click')" variant="primary">
            Browse Library
          </BaseButton>
        </div>
      </div>
    </BaseCard>

    <!-- Mission / Vision / Goal (Compact) -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <BaseCard class="!bg-emerald-900 text-white !p-6 border-none hover:shadow-lg transition-shadow">
            <h3 class="text-xs font-bold text-emerald-400 uppercase mb-2">Vision</h3>
            <p class="text-sm leading-relaxed">A prime mover of sustainable rootcrops industry.</p>
        </BaseCard>
        <BaseCard class="!bg-teal-700 text-white !p-6 border-none hover:shadow-lg transition-shadow">
            <h3 class="text-xs font-bold text-teal-300 uppercase mb-2">Mission</h3>
            <p class="text-sm leading-relaxed">To develop efficient root crops production and utilization systems.</p>
        </BaseCard>
        <BaseCard class="!bg-emerald-700 text-white !p-6 border-none hover:shadow-lg transition-shadow">
            <h3 class="text-xs font-bold text-emerald-200 uppercase mb-2">Goal</h3>
            <p class="text-sm leading-relaxed">Increase productivity and organizational capacity.</p>
        </BaseCard>
        <BaseCard class="!bg-teal-600 text-white !p-6 border-none hover:shadow-lg transition-shadow">
            <h3 class="text-xs font-bold text-teal-100 uppercase mb-2">Objective</h3>
            <p class="text-sm leading-relaxed">Develop profitable and sustainable root crop industry.</p>
        </BaseCard>
    </div>

    <!-- Divisions & Services -->
    <div class="space-y-6">
         <div class="flex items-center gap-4">
            <h2 class="text-lg font-bold text-gray-900">Research Divisions</h2>
            <div class="h-px bg-gray-200 flex-1"></div>
         </div>

         <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <BaseCard class="group hover:border-emerald-500 transition-colors">
              <div class="mb-3 w-10 h-10 bg-emerald-50 rounded-lg flex items-center justify-center text-xl">🧬</div>
              <h3 class="font-bold text-gray-900 mb-2">Crop Improvement</h3>
              <p class="text-xs text-gray-500">Evaluate varieties, maintain germplasm, clean up services.</p>
            </BaseCard>

            <BaseCard class="group hover:border-emerald-500 transition-colors">
              <div class="mb-3 w-10 h-10 bg-emerald-50 rounded-lg flex items-center justify-center text-xl">🌱</div>
              <h3 class="font-bold text-gray-900 mb-2">Crop Management</h3>
              <p class="text-xs text-gray-500">Production techniques, pest control, soil analysis.</p>
            </BaseCard>

            <BaseCard class="group hover:border-emerald-500 transition-colors">
              <div class="mb-3 w-10 h-10 bg-emerald-50 rounded-lg flex items-center justify-center text-xl">🏭</div>
              <h3 class="font-bold text-gray-900 mb-2">Processing</h3>
              <p class="text-xs text-gray-500">Postharvest tech, product utilization, waste management.</p>
            </BaseCard>
         </div>
    </div>
  </div>
</template>

<style scoped>
.animate-fade-in {
  animation: fadeIn 0.4s ease-out;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>