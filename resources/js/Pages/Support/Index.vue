<template>
  <StorefrontLayout>
    <div class="mx-auto max-w-5xl space-y-6">
      <div class="space-y-2">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Support') }}</p>
        <h1 class="text-3xl font-semibold tracking-tight text-slate-900">{{ t('We are here to help') }}</h1>
        <p class="text-sm text-slate-600">
          {{ t('For order issues, customs questions, or delivery updates, reach out and we will respond quickly.') }}
        </p>
      </div>

      <div class="grid gap-6 lg:grid-cols-[1.5fr_1fr]">
        <div class="card p-6">
          <div class="mb-4 flex flex-wrap items-center gap-3">
            <span class="rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-slate-600">
              {{ t('Live chat') }}
            </span>
            <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="agentBadgeClass">
              {{ statusLabel }}
            </span>
            <button
              v-if="isLoggedIn && !isAiOnly && agentType !== 'human'"
              type="button"
              class="btn-ghost text-xs"
              @click="requestHumanAgent"
            >
              {{ t('Talk to human') }}
            </button>
          </div>

          <div v-if="!isLoggedIn" class="space-y-4">
            <p class="text-sm text-slate-600">
              {{ t('Sign in to start live chat and keep your support history linked to your account.') }}
            </p>
            <div class="flex gap-3">
              <Link href="/login" class="btn-primary">{{ t('Sign in') }}</Link>
              <Link href="/register" class="btn-ghost">{{ t('Create account') }}</Link>
            </div>
          </div>

          <div v-else class="space-y-4">
            <div ref="chatListRef" class="max-h-[440px] space-y-3 overflow-y-auto rounded-2xl border border-slate-200 bg-slate-50 p-4">
              <div v-if="chatMessages.length === 0" class="rounded-xl border border-dashed border-slate-300 bg-white p-4 text-sm text-slate-500">
                {{ t('Start the conversation. Include your order number for faster help.') }}
              </div>

              <div
                v-for="message in chatMessages"
                :key="message.id"
                class="flex"
                :class="message.senderType === 'customer' ? 'justify-end' : 'justify-start'"
              >
                <div
                  class="max-w-[82%] rounded-2xl px-4 py-3 text-sm leading-6 shadow-sm"
                  :class="message.senderType === 'customer'
                    ? 'bg-slate-900 text-white'
                    : message.senderType === 'system'
                      ? 'border border-amber-200 bg-amber-50 text-amber-900'
                      : 'border border-slate-200 bg-white text-slate-800'"
                >
                  <template v-if="message.messageType === 'image' && message.metadata?.attachment_url">
                    <a :href="message.metadata.attachment_url" target="_blank" rel="noopener noreferrer" class="mb-2 block">
                      <img
                        :src="message.metadata.attachment_url"
                        :alt="message.metadata?.attachment_name || 'Attachment'"
                        class="max-h-52 w-auto max-w-full rounded-xl border border-black/10 object-cover"
                      >
                    </a>
                  </template>

                  <template v-if="message.messageType === 'file' && message.metadata?.attachment_url">
                    <a
                      :href="message.metadata.attachment_url"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="mb-2 inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700"
                    >
                      <span>{{ message.metadata?.attachment_name || t('Open attachment') }}</span>
                    </a>
                  </template>

                  {{ message.body }}
                </div>
              </div>
            </div>

            <div class="flex flex-wrap gap-2">
              <button
                v-for="issue in quickIssues"
                :key="issue"
                type="button"
                class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 hover:border-slate-300"
                @click="sendChatMessage(issue)"
              >
                {{ issue }}
              </button>
            </div>

            <div class="flex gap-2">
              <input
                v-model="inputMessage"
                type="text"
                class="input h-11 flex-1"
                :placeholder="t('Type your message')"
                @keydown.enter.prevent="sendChatMessage()"
              >
              <input
                ref="attachmentInputRef"
                type="file"
                class="hidden"
                accept="image/jpeg,image/png,image/webp,application/pdf,text/plain"
                @change="handleAttachmentSelected"
              >
              <button
                type="button"
                class="btn-ghost h-11 px-4"
                :disabled="sending"
                @click="openAttachmentPicker"
              >
                {{ sending ? t('Uploading...') : t('Attach') }}
              </button>
              <button
                type="button"
                class="btn-primary h-11 px-5"
                :disabled="sending || !inputMessage.trim()"
                @click="sendChatMessage()"
              >
                {{ sending ? t('Sending...') : t('Send') }}
              </button>
            </div>

            <p v-if="chatError" class="text-xs text-rose-600">{{ chatError }}</p>
          </div>
        </div>

        <div class="space-y-4">
          <div class="card p-5">
            <div class="space-y-4">
              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Email') }}</p>
                <p class="text-sm font-semibold text-slate-900">{{ supportEmail }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('WhatsApp') }}</p>
                <p class="text-sm font-semibold text-slate-900">{{ supportWhatsApp }}</p>
              </div>
              <div v-if="supportPhone">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Phone') }}</p>
                <p class="text-sm font-semibold text-slate-900">{{ supportPhone }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Hours') }}</p>
                <p class="text-sm text-slate-600">{{ supportHours }}</p>
              </div>
            </div>
          </div>

          <div class="card-muted p-5 text-sm text-slate-600">
            {{ t('Include your order number and the email used at checkout. For customs queries, mention your city and tracking number if available.') }}
          </div>
        </div>
      </div>
    </div>
  </StorefrontLayout>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import { useTranslations } from '@/i18n'

const page = usePage()
const { t } = useTranslations()

const supportEmail = page.props.site?.support_email ?? 'support@dispatch.store'
const supportWhatsApp = page.props.site?.support_whatsapp ?? '+225 00 00 00 00'
const supportPhone = page.props.site?.support_phone ?? null
const supportHours = page.props.site?.support_hours ?? 'Mon-Sat, 9:00-18:00 GMT'

const isLoggedIn = computed(() => Boolean(page.props.auth?.user))
const isAiOnly = computed(() => Boolean(page.props.supportChatRealtime?.ai_only ?? true))
const quickIssues = [
  'Track my order',
  'Payment problem',
  'Return or refund',
  'Account access',
]

const sessionId = ref(null)
const agentType = ref('ai')
const chatStatus = ref('idle')
const chatMessages = ref([])
const inputMessage = ref('')
const sending = ref(false)
const chatError = ref('')
const chatListRef = ref(null)
const attachmentInputRef = ref(null)
const realtimeConnected = ref(false)
const realtimeChannel = ref(null)
const realtimeClient = ref(null)
const realtimeScriptLoading = ref(false)

let pollTimer = null

const statusLabel = computed(() => {
  if (!isLoggedIn.value) return t('Guest mode')
  if (chatStatus.value === 'connecting') return t('Connecting')
  return agentType.value === 'human' ? t('Human agent') : t('AI assistant')
})

const agentBadgeClass = computed(() => {
  if (!isLoggedIn.value) return 'bg-slate-100 text-slate-700'
  if (chatStatus.value === 'connecting') return 'bg-blue-100 text-blue-700'
  return agentType.value === 'human' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'
})

function normalizePayload(response) {
  return response?.data?.data ?? response?.data ?? {}
}

function mapServerMessage(message) {
  return {
    id: `srv-${message.id}`,
    serverId: Number(message.id),
    senderType: String(message.sender_type ?? 'system'),
    body: String(message.body ?? ''),
    createdAt: message.created_at ?? null,
    messageType: String(message.message_type ?? 'text'),
    metadata: message.metadata && typeof message.metadata === 'object' ? message.metadata : null,
  }
}

function isSessionClosedError(error) {
  const status = Number(error?.response?.status ?? 0)
  if (status !== 409) return false

  const payload = error?.response?.data ?? {}
  if (payload?.data?.requires_new_session) return true

  return Array.isArray(payload?.errors?.session_id) && payload.errors.session_id.includes('Session closed')
}

async function restartResolvedSession() {
  disconnectRealtime()
  stopPolling()
  sessionId.value = null
  agentType.value = isAiOnly.value ? 'ai' : 'auto'
  chatStatus.value = 'idle'
  chatMessages.value = []
  await startChat('auto')

  return Boolean(sessionId.value)
}

function mergeMessages(messages) {
  if (!Array.isArray(messages) || messages.length === 0) return

  const existing = new Map(chatMessages.value.map((message) => [message.id, message]))
  for (const raw of messages) {
    const mapped = mapServerMessage(raw)
    existing.set(mapped.id, mapped)
  }

  chatMessages.value = [...existing.values()].sort((a, b) => {
    if (a.serverId && b.serverId) return a.serverId - b.serverId
    return String(a.id).localeCompare(String(b.id))
  })

  nextTick(() => {
    if (chatListRef.value) {
      chatListRef.value.scrollTop = chatListRef.value.scrollHeight
    }
  })
}

function lastServerMessageId() {
  let max = 0
  for (const message of chatMessages.value) {
    if (!message.serverId) continue
    if (message.serverId > max) max = message.serverId
  }
  return max > 0 ? max : undefined
}

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}

function startPolling() {
  startPollingWithInterval(5000)
}

function startPollingWithInterval(intervalMs = 5000) {
  stopPolling()
  pollTimer = setInterval(() => {
    pollMessages()
  }, intervalMs)
}

async function startChat(agent = 'auto') {
  if (!isLoggedIn.value) return

  chatStatus.value = 'connecting'
  chatError.value = ''

  try {
    const response = await axios.post('/support/chat/start', { agent })
    const payload = normalizePayload(response)
    sessionId.value = payload.session_id ?? sessionId.value
    agentType.value = payload.agent_type ?? 'ai'
    chatStatus.value = 'connected'
    mergeMessages(payload.messages ?? [])
    await initializeRealtimeAndPolling()
  } catch (error) {
    chatStatus.value = 'idle'
    chatError.value = error?.response?.data?.message ?? t('Unable to start support chat right now.')
    realtimeConnected.value = false
    startPollingWithInterval(5000)
  }
}

async function sendChatMessage(quickText = null, retryCount = 0) {
  const text = (quickText ?? inputMessage.value).trim()
  if (!text || sending.value || !isLoggedIn.value) return

  if (!sessionId.value) {
    await startChat('auto')
  }

  if (!sessionId.value) {
    chatError.value = t('Unable to create support session.')
    return
  }

  if (!quickText) {
    inputMessage.value = ''
  }

  sending.value = true
  chatError.value = ''

  try {
    const response = await axios.post('/support/chat/respond', {
      session_id: sessionId.value,
      input: text,
    })
    const payload = normalizePayload(response)
    agentType.value = payload.agent_type ?? agentType.value
    chatStatus.value = 'connected'
    mergeMessages(payload.messages ?? [])
  } catch (error) {
    if (retryCount < 1 && isSessionClosedError(error)) {
      chatError.value = t('Your previous support session was resolved. Starting a new session.')
      const restarted = await restartResolvedSession()
      if (restarted) {
        await sendChatMessage(text, retryCount + 1)
        return
      }
    }

    chatError.value = error?.response?.data?.message ?? t('Unable to send your message right now.')
  } finally {
    sending.value = false
  }
}

function openAttachmentPicker() {
  if (sending.value) return
  attachmentInputRef.value?.click()
}

async function uploadAttachment(file, caption = '', retryCount = 0) {
  const formData = new FormData()
  formData.append('session_id', sessionId.value)
  if (caption.trim()) {
    formData.append('message', caption.trim())
  }
  formData.append('file', file)

  try {
    const response = await axios.post('/support/chat/attachment', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })

    const payload = normalizePayload(response)
    agentType.value = payload.agent_type ?? agentType.value
    mergeMessages(payload.messages ?? [])
    return true
  } catch (error) {
    if (retryCount < 1 && isSessionClosedError(error)) {
      chatError.value = t('Your previous support session was resolved. Starting a new session.')
      const restarted = await restartResolvedSession()
      if (restarted) {
        return uploadAttachment(file, caption, retryCount + 1)
      }
    }

    chatError.value = error?.response?.data?.message ?? t('Unable to upload attachment right now.')
    return false
  }
}

async function handleAttachmentSelected(event) {
  const input = event?.target
  const file = input?.files?.[0]
  if (!file || sending.value || !isLoggedIn.value) {
    if (input) input.value = ''
    return
  }

  if (!sessionId.value) {
    await startChat('human')
  }

  if (!sessionId.value) {
    chatError.value = t('Unable to create support session.')
    if (input) input.value = ''
    return
  }

  sending.value = true
  chatError.value = ''

  try {
    const caption = inputMessage.value
    const sent = await uploadAttachment(file, caption)
    if (sent && caption.trim()) {
      inputMessage.value = ''
    }
  } finally {
    sending.value = false
    if (input) input.value = ''
  }
}

async function requestHumanAgent() {
  if (isAiOnly.value) {
    chatError.value = t('AI support is enabled for this store.')
    return
  }

  if (!sessionId.value) {
    await startChat('human')
    return
  }

  try {
    const response = await axios.post('/support/chat/forward', {
      session_id: sessionId.value,
      message: t('I need a human support agent.'),
    })
    const payload = normalizePayload(response)
    agentType.value = payload.agent_type ?? 'human'
    mergeMessages(payload.messages ?? [])
  } catch (error) {
    if (isSessionClosedError(error)) {
      chatError.value = t('Your previous support session was resolved. Starting a new session.')
      const restarted = await restartResolvedSession()
      if (restarted) {
        await requestHumanAgent()
        return
      }
    }

    chatError.value = error?.response?.data?.message ?? t('Unable to request a human agent now.')
  }
}

async function pollMessages() {
  if (!sessionId.value || !isLoggedIn.value) return

  try {
    const response = await axios.get('/support/chat/messages', {
      params: {
        session_id: sessionId.value,
        after_id: lastServerMessageId(),
        limit: 50,
      },
    })
    const payload = normalizePayload(response)
    agentType.value = payload.agent_type ?? agentType.value
    mergeMessages(payload.messages ?? [])
  } catch (error) {
    if (isSessionClosedError(error)) {
      await restartResolvedSession()
    }
  }
}

function supportRealtimeConfig() {
  const cfg = page.props.supportChatRealtime ?? {}
  return {
    enabled: Boolean(cfg.enabled),
    driver: String(cfg.driver ?? ''),
    key: String(cfg.key ?? ''),
    cluster: String(cfg.cluster ?? ''),
    wsHost: String(cfg.ws_host ?? '').trim(),
    wsPort: Number(cfg.ws_port ?? 443),
    wssPort: Number(cfg.wss_port ?? 443),
    forceTLS: Boolean(cfg.force_tls ?? true),
  }
}

async function ensurePusherLoaded() {
  if (window.Pusher) return true
  if (realtimeScriptLoading.value) return false

  realtimeScriptLoading.value = true
  try {
    await new Promise((resolve, reject) => {
      const script = document.createElement('script')
      script.src = 'https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js'
      script.async = true
      script.onload = () => resolve(true)
      script.onerror = () => reject(new Error('Failed to load Pusher client'))
      document.head.appendChild(script)
    })

    return Boolean(window.Pusher)
  } catch {
    return false
  } finally {
    realtimeScriptLoading.value = false
  }
}

function disconnectRealtime() {
  try {
    if (realtimeChannel.value && sessionId.value) {
      realtimeClient.value?.unsubscribe(`private-support.customer.${sessionId.value}`)
    }
  } catch {
    // noop
  }

  try {
    realtimeClient.value?.disconnect?.()
  } catch {
    // noop
  }

  realtimeChannel.value = null
  realtimeClient.value = null
  realtimeConnected.value = false
}

async function connectRealtime() {
  disconnectRealtime()

  const config = supportRealtimeConfig()
  if (!config.enabled || config.driver !== 'pusher' || !sessionId.value || !config.key) {
    return false
  }

  const loaded = await ensurePusherLoaded()
  if (!loaded || !window.Pusher) {
    return false
  }

  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
  const options = {
    cluster: config.cluster || undefined,
    wsHost: config.wsHost || undefined,
    wsPort: Number.isFinite(config.wsPort) ? config.wsPort : 443,
    wssPort: Number.isFinite(config.wssPort) ? config.wssPort : 443,
    forceTLS: config.forceTLS,
    enabledTransports: ['ws', 'wss'],
    channelAuthorization: {
      endpoint: '/broadcasting/auth',
      transport: 'ajax',
      headers: csrf ? { 'X-CSRF-TOKEN': csrf } : {},
    },
  }

  realtimeClient.value = new window.Pusher(config.key, options)
  const channelName = `private-support.customer.${sessionId.value}`
  realtimeChannel.value = realtimeClient.value.subscribe(channelName)

  realtimeChannel.value.bind('support.message.created', (payload) => {
    if (payload?.conversation_uuid !== sessionId.value) return
    if (payload?.message && typeof payload.message === 'object') {
      mergeMessages([payload.message])
    }
  })

  const connected = await new Promise((resolve) => {
    let done = false
    const finish = (ok) => {
      if (done) return
      done = true
      resolve(ok)
    }

    realtimeChannel.value.bind('pusher:subscription_succeeded', () => finish(true))
    realtimeChannel.value.bind('pusher:subscription_error', () => finish(false))
    setTimeout(() => finish(false), 4000)
  })

  realtimeConnected.value = Boolean(connected)
  if (!realtimeConnected.value) {
    disconnectRealtime()
  }

  return realtimeConnected.value
}

async function initializeRealtimeAndPolling() {
  const connected = await connectRealtime()
  startPollingWithInterval(connected ? 15000 : 5000)
}

watch(isLoggedIn, (value) => {
  if (!value) {
    stopPolling()
    disconnectRealtime()
    sessionId.value = null
    chatMessages.value = []
  }
})

onMounted(() => {
  if (isLoggedIn.value) {
    startChat('auto')
  }
})

onBeforeUnmount(() => {
  stopPolling()
  disconnectRealtime()
})
</script>
