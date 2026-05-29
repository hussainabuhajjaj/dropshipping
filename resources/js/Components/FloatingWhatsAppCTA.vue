<template>
  <div class="fixed bottom-20 right-4 z-[130] flex flex-col items-end gap-2 lg:bottom-6">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 translate-y-4"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 translate-y-4"
    >
      <div
        v-if="showTooltip"
        class="mb-1 rounded-xl bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-xl ring-1 ring-slate-200/50"
      >
        <p>{{ t('Commande simple en 2 clics') }}</p>
      </div>
    </Transition>

    <button
      type="button"
      class="group relative flex min-h-14 items-center gap-3 rounded-full bg-[#25D366] px-5 py-3 text-sm font-bold text-white shadow-[0_8px_30px_rgba(37,211,102,0.35)] transition hover:bg-[#20BD5E] hover:shadow-[0_12px_40px_rgba(37,211,102,0.45)] active:scale-95"
      :disabled="creatingIntent"
      @click="handleClick"
    >
      <svg class="h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="currentColor">
        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
      </svg>
      <span class="whitespace-nowrap">{{ creatingIntent ? t('Préparation...') : t('Commander sur WhatsApp') }}</span>
    </button>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useTranslations } from '@/i18n'

const props = defineProps({
  showTooltip: { type: Boolean, default: true },
})

const { t } = useTranslations()
const page = usePage()
const creatingIntent = ref(false)
const showTooltip = ref(props.showTooltip)

const whatsappPhone = computed(() => {
  const phone = page.props.site?.support_whatsapp ?? '22500000000'
  return phone.replace(/[^\d]/g, '')
})

const handleClick = async () => {
  const text = encodeURIComponent(
    t('Bonjour, je souhaite commander ce produit.')
  )
  const waUrl = `https://wa.me/${whatsappPhone.value}?text=${text}`

  if (typeof window !== 'undefined' && typeof window.ttq?.track === 'function') {
    window.ttq.track('Contact', { content_name: 'whatsapp_cta_click' })
  }
  if (typeof window !== 'undefined' && typeof window.fbq === 'function') {
    window.fbq('track', 'Contact')
  }

  window.open(waUrl, '_blank')
}
</script>
