<template>
  <Transition
    enter-active-class="transition duration-300 ease-out"
    enter-from-class="opacity-0 translate-y-4"
    enter-to-class="opacity-100 translate-y-0"
    leave-active-class="transition duration-200 ease-in"
    leave-from-class="opacity-100 translate-y-0"
    leave-to-class="opacity-0 translate-y-4"
  >
    <div
      v-if="visible && currentNotification"
      class="fixed bottom-36 left-4 right-4 z-[125] max-w-xs animate-slide-up rounded-2xl border border-slate-200/80 bg-white/95 px-4 py-3 shadow-[0_12px_40px_rgba(15,23,42,0.15)] backdrop-blur sm:bottom-28 sm:left-auto sm:right-20 sm:max-w-sm"
      @click.self="dismiss"
    >
      <div class="flex items-start gap-3">
        <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#dcfce7]">
          <svg class="h-5 w-5 text-[#128C49]" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
          </svg>
        </div>
        <div class="min-w-0 flex-1">
          <p class="text-sm font-semibold text-slate-900">
            {{ currentNotification.name }}
          </p>
          <p class="mt-0.5 text-xs text-slate-500">
            {{ currentNotification.action }}
          </p>
          <p class="mt-0.5 text-[0.65rem] font-medium text-[#128C49]">
            {{ currentNotification.time }}
          </p>
        </div>
        <button
          type="button"
          class="-mr-1 -mt-1 flex h-6 w-6 items-center justify-center rounded-full text-slate-400 hover:bg-slate-100 hover:text-slate-600"
          @click="dismiss"
        >
          <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useTranslations } from '@/i18n'

const { t } = useTranslations()

const notifications = [
  { name: 'Aïcha K.', action: t('vient de commander un article à Abidjan'), time: 'Il y a 3 min' },
  { name: 'Mamadou D.', action: t('vient de commander un article à Cocody'), time: 'Il y a 7 min' },
  { name: 'Fatou S.', action: t('vient de commander un article à Yopougon'), time: 'Il y a 12 min' },
  { name: 'Ibrahim T.', action: t('vient de commander un article à Abobo'), time: 'Il y a 18 min' },
  { name: 'Aminata B.', action: t('vient de commander un article à Marcory'), time: 'Il y a 25 min' },
  { name: 'Ousmane G.', action: t('vient de commander un article à Bingerville'), time: 'Il y a 34 min' },
  { name: 'Kadiatou S.', action: t('vient de commander un article à Adjamé'), time: 'Il y a 42 min' },
  { name: 'Lamine N.', action: t('vient de commander un article à Treichville'), time: 'Il y a 51 min' },
]

const visible = ref(false)
const currentNotification = ref(null)
let currentIndex = 0
let intervalId = null

const showNext = () => {
  if (currentIndex >= notifications.length) {
    currentIndex = 0
  }
  currentNotification.value = notifications[currentIndex]
  visible.value = true
  currentIndex++

  setTimeout(() => {
    visible.value = false
  }, 5000)
}

const dismiss = () => {
  visible.value = false
}

onMounted(() => {
  setTimeout(() => {
    showNext()
    intervalId = setInterval(showNext, 25000)
  }, 5000)
})

onBeforeUnmount(() => {
  if (intervalId) {
    clearInterval(intervalId)
  }
})
</script>
