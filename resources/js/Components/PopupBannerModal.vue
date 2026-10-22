<template>
  <Modal :show="visible" maxWidth="lg" @close="dismiss">
    <div class="relative">
      <button
        type="button"
        class="absolute right-4 top-4 rounded-full bg-white/90 text-slate-900 px-3 py-1 text-xs font-semibold"
        @click="dismiss"
      >
        {{ t('Close') }}
      </button>
      <div v-if="activeBanner?.imagePath" class="aspect-[16/9] overflow-hidden rounded-t-lg">
        <img :src="activeBanner.imagePath" :alt="activeBanner.title || 'Promotion'" class="h-full w-full object-cover" />
      </div>
      <div class="p-6 space-y-4">
        <div class="flex items-center gap-3">
          <span v-if="activeBanner?.badgeText" class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900">
            {{ activeBanner.badgeText }}
          </span>
          <span v-if="activeBanner?.ends_at" class="text-xs text-slate-500">
            {{ t('Ends in :time', { time: promoCountdown(activeBanner) }) }}
          </span>
        </div>
        <h3 class="text-lg font-semibold text-slate-900">
          {{ activeBanner?.title || t('Featured promotion') }}
        </h3>
        <p v-if="activeBanner?.description" class="text-sm text-slate-600">
          {{ activeBanner.description }}
        </p>
        <div class="flex items-center gap-3">
          <Link
            v-if="activeBanner?.ctaUrl"
            :href="activeBanner.ctaUrl"
            class="inline-flex rounded-lg bg-[#29ab87] px-4 py-2 text-xs font-semibold text-white"
          >
            {{ activeBanner.ctaText || t('Shop now') }}
          </Link>
          <button
            type="button"
            class="text-xs font-semibold text-slate-500"
            @click="dismiss"
          >
            {{ t('Not now') }}
          </button>
        </div>
      </div>
    </div>
  </Modal>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import Modal from '@/Components/Modal.vue'
import { useTranslations } from '@/i18n'
import { usePromoNow, formatCountdown } from '@/composables/usePromoCountdown.js'

const props = defineProps({
  banners: { type: Array, default: () => [] },
})

const { t } = useTranslations()
const now = usePromoNow()
const visible = ref(false)
const activeBannerId = ref(null)

const activeBanner = computed(() => {
  if (!activeBannerId.value) return null
  return props.banners.find((banner) => banner.id === activeBannerId.value) || null
})

const promoCountdown = (promo) => formatCountdown(promo?.end_at, now.value) || ''

const findNextBanner = () => {
  if (!props.banners?.length) return null
  return props.banners.find((banner) => !wasDismissed(banner.id)) || null
}

const wasDismissed = (id) => {
  if (!id) return true
  try {
    return Boolean(localStorage.getItem(`popup_banner_seen_${id}`))
  } catch {
    return false
  }
}

const markDismissed = (id) => {
  if (!id) return
  try {
    localStorage.setItem(`popup_banner_seen_${id}`, new Date().toISOString())
  } catch {}
}

const show = () => {
  const banner = findNextBanner()
  if (!banner) return
  activeBannerId.value = banner.id
  visible.value = true
}

const dismiss = () => {
  if (activeBannerId.value) {
    markDismissed(activeBannerId.value)
  }
  visible.value = false
  activeBannerId.value = null
}

onMounted(() => {
  if (!props.banners?.length) return
  setTimeout(show, 800)
})
</script>
