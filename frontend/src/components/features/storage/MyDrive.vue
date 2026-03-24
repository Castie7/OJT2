<script setup lang="ts">
import WorkspaceStoragePanel from '../research/WorkspaceStoragePanel.vue'
</script>

<template>
  <div class="space-y-6 animate-fade-in">
    <div>
      <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
        <span>🗂️</span> My Drive
      </h2>
      <p class="text-sm text-gray-500">Manage your personal file storage quota (1 GB).</p>
    </div>

    <WorkspaceStoragePanel />
  </div>
</template>

<style scoped>
.animate-fade-in {
  animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(5px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>

