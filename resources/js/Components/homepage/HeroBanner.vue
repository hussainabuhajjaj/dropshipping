<template>
  <section
    class="relative overflow-hidden rounded-[1.25rem] bg-[#111111] text-white shadow-[0_18px_44px_rgba(15,23,42,0.18)] sm:rounded-[1.55rem]"
    @touchstart.passive="onTouchStart"
    @touchend.passive="onTouchEnd"
  >
    <div class="relative">
      <div class="relative aspect-[0.96] sm:aspect-[1.08] lg:aspect-[1.18]">
        <div
          v-for="(slide, index) in displaySlides"
          :key="slide.key"
          class="absolute inset-0 transition-opacity duration-300"
          :class="index === activeIndex ? 'opacity-100' : 'pointer-events-none opacity-0'"
        >
          <img
            v-if="slide.image"
            :src="slide.image"
            :alt="slide.title"
            class="h-full w-full object-cover"
            loading="lazy"
          />
          <div
            v-else
            class="h-full w-full bg-[radial-gradient(circle_at_top_right,_rgba(255,121,71,0.35),_transparent_28%),linear-gradient(135deg,#1b1b1b_0%,#111111_55%,#050505_100%)]"
          ></div>
          <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/32 to-black/12"></div>
        </div>

        <div class="absolute inset-x-0 top-0 z-10 flex gap-1.5 overflow-x-auto px-2.5 pt-2.5 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:px-4 sm:pt-4">
          <span class="shrink-0 rounded-full bg-[#ff6b35] px-3 py-1 text-[0.62rem] font-bold uppercase tracking-[0.22em] text-white">
            {{ currentSlide.kicker || t('Today only') }}
          </span>
          <span
            v-if="currentSlide.badge"
            class="shrink-0 rounded-full bg-white/12 px-3 py-1 text-[0.62rem] font-bold uppercase tracking-[0.18em] text-white/90"
          >
            {{ currentSlide.badge }}
          </span>
          <span
            v-for="stat in stats.slice(0, 2)"
            :key="`${currentSlide.key}-${stat.label}`"
            class="shrink-0 rounded-full bg-black/28 px-3 py-1 text-[0.62rem] font-semibold uppercase tracking-[0.16em] text-white/78 backdrop-blur"
          >
            {{ stat.value }} {{ stat.label }}
          </span>
        </div>

        <div class="absolute inset-x-0 bottom-0 z-10 p-2.5 sm:p-4">
          <div class="rounded-[1.1rem] border border-white/10 bg-black/26 p-3 backdrop-blur sm:rounded-[1.3rem] sm:p-4">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <h1 class="max-w-md text-[1.08rem] font-black leading-[0.92] tracking-[-0.05em] sm:text-[1.42rem] lg:text-[1.7rem]">
                  {{ currentSlide.title }}
                </h1>
                <p class="mt-1.5 max-w-lg text-[0.72rem] leading-4 text-white/78 sm:text-[0.82rem] sm:leading-5 lg:text-[0.9rem]">
                  {{ currentSlide.subtitle }}
                </p>
              </div>

              <div class="hidden shrink-0 rounded-[1rem] bg-[#facc15] px-2.5 py-1.5 text-right text-[0.62rem] font-black uppercase tracking-[0.16em] text-slate-950 sm:block">
                <div>{{ currentSlide.calloutBadge || t('Hot') }}</div>
                <div class="mt-0.5">{{ t('Now') }}</div>
              </div>
            </div>

            <div class="mt-2.5 flex flex-wrap gap-2">
              <component
                :is="primaryAction.isJump ? 'button' : Link"
                v-bind="primaryAction.bindings"
                class="inline-flex min-h-11 items-center justify-center rounded-full bg-[#ff6b35] px-4 text-[0.68rem] font-bold uppercase tracking-[0.12em] text-white shadow-[0_10px_24px_rgba(255,107,53,0.34)] transition hover:bg-[#ff5420] sm:text-[0.7rem]"
                @click="handleAction(primaryAction)"
              >
                {{ currentSlide.primary?.label || t('Shop now') }}
              </component>
              <component
                :is="secondaryAction.isJump ? 'button' : Link"
                v-bind="secondaryAction.bindings"
                class="inline-flex min-h-11 items-center justify-center rounded-full border border-white/16 bg-white/8 px-4 text-[0.68rem] font-bold uppercase tracking-[0.12em] text-white transition hover:bg-white/12 sm:text-[0.7rem]"
                @click="handleAction(secondaryAction)"
              >
                {{ currentSlide.secondary?.label || t('See trending') }}
              </component>
            </div>

            <div class="mt-2.5 grid grid-cols-2 gap-2 sm:grid-cols-4">
              <article
                v-for="highlight in displayHighlights"
                :key="`${currentSlide.key}-${highlight.title}`"
                class="rounded-[0.9rem] border border-white/10 bg-black/20 px-2.5 py-2"
              >
                <p class="text-[0.56rem] font-bold uppercase tracking-[0.18em] text-white/52">{{ highlight.eyebrow }}</p>
                <p class="mt-1 text-[0.72rem] font-bold leading-4 text-white">{{ highlight.title }}</p>
                <p class="mt-1 text-[0.64rem] leading-4 text-white/70">{{ highlight.subtitle }}</p>
              </article>
            </div>
          </div>
        </div>

        <button
          v-if="displaySlides.length > 1"
          type="button"
          class="absolute left-2 top-1/2 z-10 inline-flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full border border-white/12 bg-black/28 text-white backdrop-blur transition hover:bg-black/40 sm:left-3"
          @click="prevSlide"
        >
          ‹
        </button>
        <button
          v-if="displaySlides.length > 1"
          type="button"
          class="absolute right-2 top-1/2 z-10 inline-flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full border border-white/12 bg-black/28 text-white backdrop-blur transition hover:bg-black/40 sm:right-3"
          @click="nextSlide"
        >
          ›
        </button>
      </div>

      <div class="absolute inset-x-0 bottom-0 z-10 flex items-center justify-center gap-1.5 pb-2 sm:pb-3">
        <button
          v-for="(slide, index) in displaySlides"
          :key="`dot-${slide.key}`"
          type="button"
          class="flex h-8 min-w-8 items-center justify-center rounded-full transition-all"
          :class="index === activeIndex ? 'bg-white/18' : 'bg-transparent hover:bg-white/10'"
          @click="goToSlide(index)"
        >
          <span
            class="h-2 rounded-full transition-all"
            :class="index === activeIndex ? 'w-6 bg-white' : 'w-2 bg-white/45'"
          ></span>
        </button>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useTranslations } from '@/i18n'

const emit = defineEmits(['jump'])

const props = defineProps({
  hero: { type: Object, default: null },
  slides: { type: Array, default: () => [] },
  stats: { type: Array, default: () => [] },
  chips: { type: Array, default: () => [] },
  highlights: { type: Array, default: () => [] },
})

const { t } = useTranslations()

const touchStartX = ref(0)
const activeIndex = ref(0)

const fallbackSlide = computed(() => ({
  key: 'fallback',
  kicker: props.hero?.kicker || t('Today only'),
  badge: props.hero?.badge || '',
  title: props.hero?.title || t('Hot picks, fast deals, new drops.'),
  subtitle: props.hero?.subtitle || t('Shop Simbazu like a live fashion feed with limited-time offers, quick categories, and daily deal momentum.'),
  image: props.hero?.image || null,
  primary: props.hero?.primary || { label: t('Shop now'), href: '/products' },
  secondary: props.hero?.secondary || { label: t('See trending'), href: '#trending' },
  calloutBadge: props.hero?.calloutBadge || t('Hot'),
}))

const displaySlides = computed(() => {
  if (props.slides?.length) {
    return props.slides.map((slide, index) => ({
      key: slide.key || `slide-${index}`,
      kicker: slide.kicker || t('Today only'),
      badge: slide.badge || '',
      title: slide.title || fallbackSlide.value.title,
      subtitle: slide.subtitle || fallbackSlide.value.subtitle,
      image: slide.image || fallbackSlide.value.image,
      primary: slide.primary || fallbackSlide.value.primary,
      secondary: slide.secondary || fallbackSlide.value.secondary,
      calloutBadge: slide.calloutBadge || fallbackSlide.value.calloutBadge,
    }))
  }

  return [fallbackSlide.value]
})

const currentSlide = computed(() => displaySlides.value[activeIndex.value] || fallbackSlide.value)

const displayHighlights = computed(() => {
  if (props.highlights?.length) return props.highlights.slice(0, 4)

  return [
    { eyebrow: t('Fast delivery'), title: t('Low-friction checkout'), subtitle: t('Cart to payment without dead taps.') },
    { eyebrow: t('Trending'), title: t('High-velocity deals'), subtitle: t('Fresh promos and feed-first discovery.') },
    { eyebrow: t('Social proof'), title: t('Popular products first'), subtitle: t('More product visibility per screen.') },
    { eyebrow: t('Swipe'), title: t('Full-image drops'), subtitle: t('Carousel hero built for browse momentum.') },
  ]
})

const createAction = (action, fallback) => {
  const href = action?.href || fallback
  const isJump = typeof href === 'string' && href.startsWith('#')

  return {
    href,
    isJump,
    bindings: isJump ? { type: 'button' } : { href },
  }
}

const primaryAction = computed(() => createAction(currentSlide.value?.primary, '/products'))
const secondaryAction = computed(() => createAction(currentSlide.value?.secondary, '/promotions/flash-sales'))

const handleAction = (action) => {
  if (!action?.isJump) return
  emit('jump', action.href.replace(/^#/, ''))
}

const goToSlide = (index) => {
  activeIndex.value = index
}

const nextSlide = () => {
  activeIndex.value = (activeIndex.value + 1) % displaySlides.value.length
}

const prevSlide = () => {
  activeIndex.value = (activeIndex.value - 1 + displaySlides.value.length) % displaySlides.value.length
}

const onTouchStart = (event) => {
  touchStartX.value = event.changedTouches?.[0]?.clientX || 0
}

const onTouchEnd = (event) => {
  const endX = event.changedTouches?.[0]?.clientX || 0
  const delta = endX - touchStartX.value

  if (Math.abs(delta) < 30 || displaySlides.value.length <= 1) return

  if (delta < 0) nextSlide()
  else prevSlide()
}
</script>
