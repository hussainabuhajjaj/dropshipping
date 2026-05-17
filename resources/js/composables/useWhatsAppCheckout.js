import { ref } from 'vue'
import { toastAlert } from '@/utils/toast'

const readMetaCsrfToken = () =>
  document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''

export function useWhatsAppCheckout({ t }) {
  const creatingIntent = ref(false)
  const lastIntent = ref(null)

  const createIntent = async (payload) => {
    creatingIntent.value = true

    try {
      const response = await fetch('/api/whatsapp-intents', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': readMetaCsrfToken(),
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(payload),
      })

      const data = await response.json().catch(() => ({}))

      if (!response.ok) {
        throw new Error(data?.message || t('Unable to prepare WhatsApp checkout right now.'))
      }

      lastIntent.value = data

      return data
    } finally {
      creatingIntent.value = false
    }
  }

  const redirectToWhatsApp = async (intent) => {
    const deeplink = intent?.whatsapp_deeplink
    const fallback = intent?.whatsapp_url

    if (deeplink) {
      window.location.href = deeplink
    }

    if (fallback) {
      window.setTimeout(() => {
        window.location.href = fallback
      }, 600)
    }
  }

  const startWhatsAppCheckout = async (payload) => {
    try {
      const intent = await createIntent(payload)
      await redirectToWhatsApp(intent)
      return intent
    } catch (error) {
      toastAlert('error', error?.message || t('Unable to start WhatsApp checkout right now.'))
      throw error
    }
  }

  return {
    creatingIntent,
    lastIntent,
    createIntent,
    startWhatsAppCheckout,
  }
}
