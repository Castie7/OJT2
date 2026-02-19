<script setup lang="ts">
import type { Research } from '../../../../types'
import { formatDate } from '../../../../utils/formatters'
import { useAuthStore } from '../../../../stores/auth'

defineProps<{
    item: Research
}>()

const emit = defineEmits<{
    (e: 'click'): void
    (e: 'archive', item: Research): void
}>()

const authStore = useAuthStore()
</script>

<template>
    <tr @click="$emit('click')" class="hover:bg-emerald-50/50 cursor-pointer transition-colors">
        <td class="px-6 py-4">
            <div class="font-bold text-gray-900 text-sm line-clamp-1">{{ item.title }}</div>
            <div class="text-xs text-gray-500">{{ item.author }}</div>
        </td>
        <td class="px-6 py-4">
            <span class="inline-flex items-center px-2 py-0.5 rounded textxs font-medium bg-blue-50 text-blue-700 mb-1">
                {{ item.knowledge_type }}
            </span>
            <div class="text-xs text-gray-400">{{ formatDate(item.publication_date) }}</div>
        </td>
        <td class="px-6 py-4">
            <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded text-xs font-mono font-bold">{{ item.shelf_location || 'N/A' }}</span>
        </td>
        <td class="px-6 py-4 text-right">
            <button 
                v-if="authStore.currentUser && authStore.currentUser.role === 'admin'" 
                @click.stop="$emit('archive', item)" 
                class="text-xs text-red-500 hover:text-red-700 font-medium px-2 py-1 hover:bg-red-50 rounded"
            >
                Archive
            </button>
        </td>
    </tr>
</template>
