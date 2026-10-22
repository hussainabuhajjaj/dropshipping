<template>
  <div class="space-y-6">
    <!-- OAuth Status -->
    <div v-if="!isLinked" class="rounded-lg border border-blue-200 bg-blue-50 p-4">
      <h3 class="text-sm font-semibold text-blue-900">{{ t('Connect Google Calendar') }}</h3>
      <p class="mt-1 text-sm text-blue-700">
        {{ t('Link your Google Calendar to view events and integrations.') }}
      </p>
      <a
        :href="route('auth.google.redirect')"
        class="mt-3 inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
      >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="mr-2 h-5 w-5">
          <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
          <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
          <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
          <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
        </svg>
        {{ t('Connect with Google') }}
      </a>
    </div>

    <!-- OAuth Linked - Show Events -->
    <div v-else class="space-y-4">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-slate-900">{{ t('Your Calendar Events') }}</h3>
        <button
          @click="refreshEvents"
          :disabled="loading"
          class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
        >
          <svg
            v-if="!loading"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="1.5"
            stroke="currentColor"
            class="mr-1 inline h-4 w-4"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0eH5.604m-2.619 2.619h4.992" />
          </svg>
          {{ loading ? t('Loading...') : t('Refresh') }}
        </button>
      </div>

      <!-- Events List -->
      <div v-if="events.length" class="space-y-3">
        <div
          v-for="event in events"
          :key="event.id"
          class="rounded-lg border border-slate-200 bg-white p-4 transition hover:shadow-sm"
        >
          <div class="flex items-start justify-between">
            <div class="flex-1">
              <h4 class="font-semibold text-slate-900">{{ event.summary }}</h4>
              <p v-if="event.description" class="mt-1 text-sm text-slate-600">{{ event.description }}</p>
              <div class="mt-2 flex flex-wrap gap-2 text-xs text-slate-500">
                <span>📅 {{ formatDate(event.start) }}</span>
                <span v-if="event.end">→ {{ formatDate(event.end) }}</span>
                <span>📍 {{ event.location || 'No location' }}</span>
              </div>
            </div>
            <a
              v-if="event.htmlLink"
              :href="event.htmlLink"
              target="_blank"
              class="ml-4 inline-flex items-center rounded text-blue-600 hover:text-blue-700"
              :title="t('Open in Google Calendar')"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
              </svg>
            </a>
          </div>
        </div>
      </div>

      <!-- No Events Message -->
      <div v-else class="rounded-lg border border-slate-200 bg-slate-50 p-6 text-center">
        <p class="text-sm text-slate-600">{{ t('No upcoming events found.') }}</p>
      </div>

      <!-- Disconnect Button -->
      <div class="mt-6 flex gap-2">
        <button
          @click="disconnectGoogle"
          class="rounded-lg border border-red-300 bg-red-50 px-4 py-2 text-sm font-medium text-red-700 transition hover:bg-red-100"
        >
          {{ t('Disconnect Google Calendar') }}
        </button>
      </div>

      <!-- Error Message -->
      <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 p-4">
        <p class="text-sm text-red-700">{{ error }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useTranslations } from '@/i18n'

const { t } = useTranslations()

const isLinked = ref(false)
const events = ref([])
const loading = ref(false)
const error = ref(null)

onMounted(() => {
  checkOAuthStatus()
})

const checkOAuthStatus = async () => {
  try {
    const response = await fetch(route('api.calendar.events'))
    
    if (response.status === 401) {
      isLinked.value = false
      return
    }

    if (response.ok) {
      const data = await response.json()
      events.value = data.events || []
      isLinked.value = true
    }
  } catch (e) {
    error.value = e.message
  }
}

const refreshEvents = async () => {
  loading.value = true
  error.value = null

  try {
    const response = await fetch(route('api.calendar.events'))

    if (!response.ok) {
      throw new Error('Failed to fetch events')
    }

    const data = await response.json()
    events.value = data.events || []
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

const disconnectGoogle = async () => {
  if (!confirm(t('Are you sure you want to disconnect Google Calendar?'))) {
    return
  }

  try {
    const response = await fetch(route('auth.google.logout'), {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
    })

    if (response.ok) {
      isLinked.value = false
      events.value = []
    }
  } catch (e) {
    error.value = e.message
  }
}

const formatDate = (dateString) => {
  if (!dateString) return ''

  const date = new Date(dateString)
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>
