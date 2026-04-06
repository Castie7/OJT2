<script setup lang="ts">
import WorkspaceStoragePanel from '../research/WorkspaceStoragePanel.vue'
</script>

<template>
  <div class="my-drive-page">
    <WorkspaceStoragePanel />
  </div>
</template>

<style scoped>
.my-drive-page {
  animation: fadeIn 0.25s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(4px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
