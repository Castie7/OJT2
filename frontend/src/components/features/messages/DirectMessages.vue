<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { messageService } from '../../../services'
import { useAuthStore } from '../../../stores/auth'
import { useToast } from '../../../composables/useToast'
import type { DirectMessage, MessageConversation, User } from '../../../types'

type ContactListItem = User & {
  unread_count: number
  last_message: string
  last_message_at: string | null
}

const authStore = useAuthStore()
const { showToast } = useToast()

const POLL_INTERVAL_MS = 10000
const MAX_MESSAGE_LENGTH = 2000

const contacts = ref<User[]>([])
const conversations = ref<MessageConversation[]>([])
const messages = ref<DirectMessage[]>([])
const selectedUserId = ref<number | null>(null)
const search = ref('')
const draftMessage = ref('')

const isLoadingContacts = ref(false)
const isLoadingMessages = ref(false)
const isSending = ref(false)
const isRefreshing = ref(false)

const messageListRef = ref<HTMLElement | null>(null)
let pollTimer: number | null = null

const notifyDirectMessagesUpdated = () => {
  window.dispatchEvent(new Event('direct-messages-updated'))
}

const selectedUser = computed(() =>
  contacts.value.find(user => user.id === selectedUserId.value) ?? null
)

const conversationMap = computed(() => {
  const map = new Map<number, MessageConversation>()
  for (const conversation of conversations.value) {
    map.set(Number(conversation.user_id), conversation)
  }
  return map
})

const filteredContacts = computed<ContactListItem[]>(() => {
  const query = search.value.trim().toLowerCase()

  const rows = contacts.value.map(contact => {
    const conversation = conversationMap.value.get(contact.id)
    return {
      ...contact,
      unread_count: Number(conversation?.unread_count ?? 0),
      last_message: conversation?.last_message ?? '',
      last_message_at: conversation?.last_message_at ?? null
    }
  })

  const filtered = query
    ? rows.filter(row =>
        row.name.toLowerCase().includes(query)
        || (row.email ?? '').toLowerCase().includes(query)
      )
    : rows

  return filtered.sort((a, b) => {
    const timeA = toTimestamp(a.last_message_at)
    const timeB = toTimestamp(b.last_message_at)
    if (timeA !== timeB) return timeB - timeA
    return a.name.localeCompare(b.name)
  })
})

const toTimestamp = (value: string | null | undefined): number => {
  if (!value) return 0
  const normalized = value.includes('T') ? value : value.replace(' ', 'T')
  const date = new Date(normalized)
  return Number.isNaN(date.getTime()) ? 0 : date.getTime()
}

const formatTime = (value: string | null | undefined): string => {
  const timestamp = toTimestamp(value)
  if (!timestamp) return ''
  const date = new Date(timestamp)
  return date.toLocaleString([], {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const scrollToBottom = async () => {
  await nextTick()
  if (!messageListRef.value) return
  messageListRef.value.scrollTop = messageListRef.value.scrollHeight
}

const isMine = (message: DirectMessage) => {
  return Number(message.sender_id) === Number(authStore.currentUser?.id)
}

const loadUsers = async (silent = false) => {
  isLoadingContacts.value = !silent
  try {
    contacts.value = await messageService.getUsers()
  } catch (error: any) {
    if (!silent) {
      const msg = error?.response?.data?.message || 'Failed to load users.'
      showToast(msg, 'error')
    }
  } finally {
    isLoadingContacts.value = false
  }
}

const loadConversations = async (silent = false) => {
  try {
    conversations.value = await messageService.getConversations()
  } catch (error: any) {
    if (!silent) {
      const msg = error?.response?.data?.message || 'Failed to load conversations.'
      showToast(msg, 'error')
    }
  }
}

const loadThread = async (partnerId: number, silent = false) => {
  isLoadingMessages.value = !silent
  try {
    messages.value = await messageService.getThread(partnerId, 100)
    await scrollToBottom()
  } catch (error: any) {
    if (!silent) {
      const msg = error?.response?.data?.message || 'Failed to load messages.'
      showToast(msg, 'error')
    }
  } finally {
    isLoadingMessages.value = false
  }
}

const markConversationAsRead = async (partnerId: number, silent = true) => {
  try {
    await messageService.markAsRead({ partner_id: partnerId })
    notifyDirectMessagesUpdated()
  } catch (error: any) {
    if (!silent) {
      const msg = error?.response?.data?.message || 'Failed to update read state.'
      showToast(msg, 'error')
    }
  }
}

const selectConversation = async (partnerId: number, silent = false) => {
  selectedUserId.value = partnerId
  await loadThread(partnerId, silent)

  const active = conversationMap.value.get(partnerId)
  if (active && Number(active.unread_count) > 0) {
    await markConversationAsRead(partnerId, true)
    await loadConversations(true)
  }
}

const initialize = async () => {
  await Promise.all([loadUsers(), loadConversations()])

  if (selectedUserId.value && contacts.value.some(user => user.id === selectedUserId.value)) {
    await selectConversation(selectedUserId.value, true)
    return
  }

  const firstConversation = conversations.value[0]
  if (firstConversation && contacts.value.some(user => user.id === Number(firstConversation.user_id))) {
    await selectConversation(Number(firstConversation.user_id), true)
    return
  }

  const firstContact = contacts.value[0]
  if (firstContact) {
    await selectConversation(firstContact.id, true)
  }
}

const refreshActiveConversation = async () => {
  if (isRefreshing.value) return
  isRefreshing.value = true

  try {
    await loadConversations(true)

    if (!selectedUserId.value) return

    await loadThread(selectedUserId.value, true)

    const active = conversationMap.value.get(selectedUserId.value)
    if (active && Number(active.unread_count) > 0) {
      await markConversationAsRead(selectedUserId.value, true)
      await loadConversations(true)
    }
  } finally {
    isRefreshing.value = false
  }
}

const sendMessage = async () => {
  if (!selectedUserId.value) {
    showToast('Select an account first.', 'warning')
    return
  }

  const content = draftMessage.value.trim()
  if (!content) return

  if (content.length > MAX_MESSAGE_LENGTH) {
    showToast(`Message is too long (max ${MAX_MESSAGE_LENGTH} characters).`, 'warning')
    return
  }

  isSending.value = true
  try {
    const response = await messageService.send({
      recipient_id: selectedUserId.value,
      message: content
    })

    if (response.status !== 'success') {
      throw new Error(response.message || 'Failed to send message.')
    }

    draftMessage.value = ''
    await Promise.all([
      loadConversations(true),
      loadThread(selectedUserId.value, true)
    ])
  } catch (error: any) {
    const msg = error?.response?.data?.message || error?.message || 'Failed to send message.'
    showToast(msg, 'error')
  } finally {
    isSending.value = false
  }
}

const startPolling = () => {
  stopPolling()
  pollTimer = window.setInterval(() => {
    void refreshActiveConversation()
  }, POLL_INTERVAL_MS)
}

const stopPolling = () => {
  if (pollTimer) {
    window.clearInterval(pollTimer)
    pollTimer = null
  }
}

watch(() => messages.value.length, () => {
  void scrollToBottom()
})

onMounted(async () => {
  try {
    await messageService.markAllAsRead()
    notifyDirectMessagesUpdated()
  } catch (_error) {
    // Ignore here; page can still function and specific thread reads still work.
  }

  await initialize()
  startPolling()
})

onUnmounted(() => {
  stopPolling()
})
</script>

<template>
  <div class="h-[calc(100vh-10rem)] min-h-[36rem]">
    <div class="grid h-full grid-cols-1 gap-4 lg:grid-cols-3">
      <aside class="flex h-full flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm lg:col-span-1">
        <div class="border-b border-gray-100 p-4">
          <h2 class="text-lg font-semibold text-gray-800">Accounts</h2>
          <p class="mt-1 text-xs text-gray-500">Direct messages with registered users</p>
          <input
            v-model="search"
            type="text"
            placeholder="Search account..."
            class="mt-3 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100"
          />
        </div>

        <div class="flex-1 overflow-y-auto">
          <div v-if="isLoadingContacts" class="p-4 text-sm text-gray-500">Loading accounts...</div>
          <div v-else-if="filteredContacts.length === 0" class="p-4 text-sm text-gray-500">No accounts found.</div>

          <button
            v-for="contact in filteredContacts"
            :key="contact.id"
            @click="selectConversation(contact.id)"
            class="w-full border-b border-gray-50 px-4 py-3 text-left transition hover:bg-emerald-50/40"
            :class="selectedUserId === contact.id ? 'bg-emerald-50' : ''"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-gray-800">{{ contact.name }}</p>
                <p class="truncate text-xs capitalize text-gray-500">{{ contact.role }}</p>
                <p v-if="contact.last_message" class="mt-1 truncate text-xs text-gray-500">
                  {{ contact.last_message }}
                </p>
              </div>

              <div class="shrink-0 text-right">
                <p v-if="contact.last_message_at" class="text-[11px] text-gray-400">
                  {{ formatTime(contact.last_message_at) }}
                </p>
                <span
                  v-if="contact.unread_count > 0"
                  class="mt-1 inline-flex min-w-5 items-center justify-center rounded-full bg-emerald-600 px-1.5 py-0.5 text-[10px] font-semibold text-white"
                >
                  {{ contact.unread_count }}
                </span>
              </div>
            </div>
          </button>
        </div>
      </aside>

      <section class="flex h-full flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm lg:col-span-2">
        <div v-if="!selectedUser" class="flex h-full items-center justify-center px-6 text-center text-sm text-gray-500">
          Select an account to start direct messaging.
        </div>

        <template v-else>
          <header class="border-b border-gray-100 px-4 py-3">
            <h3 class="text-sm font-semibold text-gray-900">{{ selectedUser.name }}</h3>
            <p class="text-xs capitalize text-gray-500">{{ selectedUser.role }}</p>
          </header>

          <div ref="messageListRef" class="flex-1 overflow-y-auto bg-gray-50 p-4">
            <div v-if="isLoadingMessages" class="text-sm text-gray-500">Loading messages...</div>

            <div v-else-if="messages.length === 0" class="text-sm text-gray-500">
              No messages yet. Start the conversation.
            </div>

            <div v-else class="space-y-3">
              <div
                v-for="message in messages"
                :key="message.id"
                class="flex"
                :class="isMine(message) ? 'justify-end' : 'justify-start'"
              >
                <div
                  class="max-w-[85%] rounded-xl px-3 py-2 shadow-sm sm:max-w-[70%]"
                  :class="isMine(message) ? 'bg-emerald-600 text-white' : 'bg-white text-gray-800'"
                >
                  <p class="whitespace-pre-wrap break-words text-sm">{{ message.message }}</p>
                  <p
                    class="mt-1 text-[11px]"
                    :class="isMine(message) ? 'text-emerald-100' : 'text-gray-400'"
                  >
                    {{ formatTime(message.created_at) }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <footer class="border-t border-gray-100 p-3">
            <div class="flex items-end gap-2">
              <textarea
                v-model="draftMessage"
                rows="2"
                maxlength="2000"
                placeholder="Type a message..."
                class="min-h-10 flex-1 resize-none rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100"
                @keydown.enter.exact.prevent="sendMessage"
              />
              <button
                type="button"
                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="isSending || !draftMessage.trim()"
                @click="sendMessage"
              >
                {{ isSending ? 'Sending...' : 'Send' }}
              </button>
            </div>
            <p class="mt-1 text-right text-[11px] text-gray-400">{{ draftMessage.length }}/2000</p>
          </footer>
        </template>
      </section>
    </div>
  </div>
</template>
