<script setup lang="ts">
import { useHomeView, type User, type Stat } from '../composables/useHomeView'

defineProps<{
  currentUser: User | null // id, name, role
  stats: Stat[] // title, value, color
}>()

const emit = defineEmits<{
  (e: 'browse-click'): void
  (e: 'stat-click', tab: string): void
}>()

const { 
  recentResearches, currentSlide, nextSlide, prevSlide, 
  startSlideTimer, stopSlideTimer 
} = useHomeView()
</script>

<template>
  <div class="space-y-8"> 
    
    <div v-if="currentUser">
      <h1 class="text-2xl font-bold text-gray-900 mb-4">
        {{ currentUser.role === 'admin' ? '📢 System Overview (Admin)' : '👋 My Research Overview' }}
      </h1>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div 
            v-for="stat in stats" 
            :key="stat.id || stat.title" 
            @click="stat.action ? emit('stat-click', stat.action) : null"
            :class="[
              'bg-white p-6 rounded-lg shadow border-l-4 transition-transform duration-200',
              stat.color === 'text-red-600' ? 'border-red-500' : 
              stat.color === 'text-orange-500' ? 'border-orange-500' : 
              stat.color === 'text-yellow-600' ? 'border-yellow-500' : 'border-green-500',
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
          A prime mover of sustainable rootcrops industry
        </p>
      </div>

      <div class="bg-yellow-500 text-green-900 p-8 rounded-xl shadow-lg relative overflow-hidden group hover:shadow-2xl transition duration-300">
        <div class="absolute top-0 right-0 w-24 h-24 bg-white opacity-20 rounded-bl-full transition group-hover:scale-110 duration-500"></div>
        <div class="flex items-center gap-3 mb-4">
          <span class="text-3xl">🎯</span>
          <h2 class="text-2xl font-bold tracking-wide">MISSION</h2>
        </div>
        <p class="text-green-900 leading-relaxed text-lg font-medium">
          To develop efficient root crops production and utilization systems
        </p>
      </div>
    </div>
    
    <div class="space-y-16 py-8">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
          <div class="bg-green-800 text-white p-8 rounded-xl shadow-lg relative overflow-hidden group hover:shadow-2xl transition duration-300 border-b-4 border-yellow-500">
            <div class="absolute top-0 right-0 w-24 h-24 bg-white opacity-5 rounded-bl-full transition group-hover:scale-110 duration-500"></div>
            <div class="flex items-center gap-3 mb-4">
              <span class="text-3xl">🚀</span>
              <h2 class="text-2xl font-bold tracking-wide">GOAL</h2>
            </div>
            <p class="text-green-50 leading-relaxed text-lg">
              To increase productivity, intensify pro-active extension, develop diversified utilization of rootcrops, strengthen linkages, and improve organizational capacity.
            </p>
          </div>

          <div class="bg-yellow-100 text-green-900 p-8 rounded-xl shadow-lg relative overflow-hidden group hover:shadow-2xl transition duration-300 border-b-4 border-green-800">
            <div class="absolute top-0 right-0 w-24 h-24 bg-green-900 opacity-10 rounded-bl-full transition group-hover:scale-110 duration-500"></div>
            <div class="flex items-center gap-3 mb-4">
              <span class="text-3xl">📌</span>
              <h2 class="text-2xl font-bold tracking-wide">OBJECTIVE</h2>
            </div>
            <p class="text-green-900 leading-relaxed text-lg font-medium">
              Develop a profitable and sustainable root crop production and industry thru the generation of applicable technologies and useful information from its research, training, extension, and production activities.
            </p>
          </div>
        </div>

        <div>
          <div class="flex items-center gap-4 mb-8">
            <div class="h-1 flex-1 bg-gray-200"></div>
            <h2 class="text-3xl font-bold text-green-900 uppercase tracking-wider text-center">Divisions</h2>
            <div class="h-1 flex-1 bg-gray-200"></div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            
            <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-xl transition duration-300 border-l-4 border-green-600 group">
              <h3 class="text-xl font-bold text-green-800 mb-3 group-hover:text-green-600 transition">🧬 Crop Improvement</h3>
              <ul class="space-y-2 text-gray-600 text-sm list-disc pl-4 marker:text-green-500">
                <li>Evaluate, improve and develop varieties of root crops</li>
                <li>Maintain promising germplasm of rootcrops</li>
                <li>Provide clean up services of root crops’ parent materials</li>
                <li>Provide expert services on crop improvement</li>
              </ul>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-xl transition duration-300 border-l-4 border-green-600 group">
              <h3 class="text-xl font-bold text-green-800 mb-3 group-hover:text-green-600 transition">🌱 Crop Management & Seed Prod.</h3>
              <ul class="space-y-2 text-gray-600 text-sm list-disc pl-4 marker:text-green-500">
                <li>Develop/improve production techniques and cropping systems</li>
                <li>Integrated crop management strategies for pest control</li>
                <li>Multiply and distribute seed board approved varieties</li>
                <li>Institutionalize use of generation zero and rooted stem cuttings</li>
                <li>Soil analysis and pest diagnosis</li>
              </ul>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-xl transition duration-300 border-l-4 border-green-600 group">
              <h3 class="text-xl font-bold text-green-800 mb-3 group-hover:text-green-600 transition">🏭 Postharvest & Processing</h3>
              <ul class="space-y-2 text-gray-600 text-sm list-disc pl-4 marker:text-green-500">
                <li>Develop suitable harvest and postharvest handling technologies</li>
                <li>Expand root crops processing and utilization</li>
                <li>Develop root crop waste management systems</li>
                <li>Processing systems utilizing agricultural wastes</li>
              </ul>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-xl transition duration-300 border-l-4 border-yellow-500 group">
              <h3 class="text-xl font-bold text-green-800 mb-3 group-hover:text-yellow-600 transition">⚙️ Engineering</h3>
              <ul class="space-y-2 text-gray-600 text-sm list-disc pl-4 marker:text-yellow-500">
                <li>Design and develop low-cost efficient machineries</li>
                <li>Tools and facilities development for production</li>
                <li>Postharvest and processing equipment design</li>
              </ul>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-xl transition duration-300 border-l-4 border-yellow-500 group">
              <h3 class="text-xl font-bold text-green-800 mb-3 group-hover:text-yellow-600 transition">📊 Socio-Economics & Policy</h3>
              <ul class="space-y-2 text-gray-600 text-sm list-disc pl-4 marker:text-yellow-500">
                <li>Agro-economic database, credit and policy research</li>
                <li>Market research and social marketing studies</li>
                <li>Indigenous knowledge and gender studies</li>
                <li>Consumption and nutrition impact studies</li>
                <li>Draft policies for IP rights protection</li>
              </ul>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-xl transition duration-300 border-l-4 border-yellow-500 group">
              <h3 class="text-xl font-bold text-green-800 mb-3 group-hover:text-yellow-600 transition">📢 Training & Extension</h3>
              <ul class="space-y-2 text-gray-600 text-sm list-disc pl-4 marker:text-yellow-500">
                <li>Conduct/coordinate training and extension programs</li>
                <li>Coordinate publication of IEC materials</li>
                <li>Conduct conferences, symposia, and seminars</li>
                <li>Provide library and visitor support services</li>
                <li>Spearhead submission to refereed journals</li>
              </ul>
            </div>

          </div>
        </div>

        <div class="bg-green-50 p-8 rounded-2xl border border-green-100">
          <div class="text-center mb-8">
            <span class="bg-green-200 text-green-800 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-widest">Support</span>
            <h2 class="text-3xl font-bold text-green-900 mt-2">Technical Services</h2>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            
            <div class="bg-white p-5 rounded-lg shadow-sm border border-gray-100 flex flex-col items-center text-center hover:border-green-400 transition cursor-default">
              <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center text-2xl mb-3">🌿</div>
              <h4 class="font-bold text-gray-800 mb-1">Planting Materials</h4>
              <p class="text-xs text-gray-500">Tissue-cultured root/tuber plants & Basic planting materials</p>
            </div>

            <div class="bg-white p-5 rounded-lg shadow-sm border border-gray-100 flex flex-col items-center text-center hover:border-green-400 transition cursor-default">
              <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center text-2xl mb-3">🔬</div>
              <h4 class="font-bold text-gray-800 mb-1">Plant Disease Clinic</h4>
              <p class="text-xs text-gray-500">Disease diagnosis & Soil analysis for bacterial wilt</p>
            </div>

            <div class="bg-white p-5 rounded-lg shadow-sm border border-gray-100 flex flex-col items-center text-center hover:border-green-400 transition cursor-default">
              <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center text-2xl mb-3">🧪</div>
              <h4 class="font-bold text-gray-800 mb-1">ELISA Testing</h4>
              <p class="text-xs text-gray-500">DAS & NCM ELISA services</p>
            </div>

            <div class="bg-white p-5 rounded-lg shadow-sm border border-gray-100 flex flex-col items-center text-center hover:border-green-400 transition cursor-default">
              <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center text-2xl mb-3">🍠</div>
              <h4 class="font-bold text-gray-800 mb-1">Specific Crops</h4>
              <p class="text-xs text-gray-500">Potato bacterial wilt, Sweet potato viruses, Yam</p>
            </div>

          </div>
        </div>

      </div>
  </div>
</template>

<style scoped src="../assets/styles/HomeView.css"></style>