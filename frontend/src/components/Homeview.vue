<script setup>
defineProps(['currentUser', 'stats'])
defineEmits(['browse-click']) 
</script>

<template>
  <div class="space-y-8"> <div v-if="currentUser">
      <h1 class="text-2xl font-bold text-gray-900 mb-4">📢 System Overview (Admin Only)</h1>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div v-for="stat in stats" :key="stat.title" class="bg-white p-6 rounded-lg shadow border-l-4 border-green-500">
          <h3 class="text-gray-500 text-sm uppercase font-semibold">{{ stat.title }}</h3>
          <p :class="`text-4xl font-bold mt-2 ${stat.color}`">{{ stat.value }}</p>
        </div>
      </div>
    </div>

    <div class="bg-white p-8 rounded-lg shadow-lg border border-gray-200 relative overflow-hidden">
      <div class="absolute top-0 right-0 -mt-4 -mr-4 w-32 h-32 bg-yellow-400 rounded-full opacity-20 blur-xl"></div>
      
      <h1 class="text-4xl font-bold text-green-800 mb-4 relative z-10">Welcome to BSU RootCrops</h1>
      <p class="text-lg text-gray-600 mb-6 relative z-10">The official repository for root crop research. Browse our open collection of research data.</p>
      <div class="flex gap-4 relative z-10">
        <button @click="$emit('browse-click')" class="bg-green-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-green-700 transition shadow-lg hover:shadow-xl transform hover:-translate-y-1">
          Browse Researches
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