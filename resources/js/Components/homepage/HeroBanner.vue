<template>
  <section class="relative overflow-hidden rounded-[1.35rem] bg-[#111111] text-white shadow-[0_20px_52px_rgba(15,23,42,0.18)] sm:rounded-[1.8rem]">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_rgba(255,121,71,0.28),_transparent_28%),radial-gradient(circle_at_left_center,_rgba(254,240,138,0.16),_transparent_24%)]"></div>
    <div class="relative space-y-3 px-3 py-3 sm:px-5 sm:py-4">
      <div class="flex gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        <span class="shrink-0 rounded-full bg-[#ff6b35] px-3 py-1 text-[0.62rem] font-bold uppercase tracking-[0.22em] text-white">
          {{ hero.kicker || t('Today only') }}
        </span>
        <span v-if="hero.badge" class="shrink-0 rounded-full bg-white/10 px-3 py-1 text-[0.62rem] font-bold uppercase tracking-[0.18em] text-white/90">
          {{ hero.badge }}
        </span>
        <span
          v-for="stat in stats.slice(0, 2)"
          :key="stat.label"
          class="shrink-0 rounded-full bg-white/8 px-3 py-1 text-[0.62rem] font-semibold uppercase tracking-[0.16em] text-white/74"
        >
          {{ stat.value }} {{ stat.label }}
        </span>
      </div>

      <div class="grid gap-3 md:grid-cols-[1.05fr_0.95fr] md:items-stretch">
        <div class="rounded-[1.2rem] border border-white/10 bg-white/7 p-3 backdrop-blur sm:rounded-[1.45rem] sm:p-4">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
              <h1 class="max-w-md text-[1.22rem] font-black leading-[0.92] tracking-[-0.05em] sm:text-[1.6rem]">
                {{ hero.title }}
              </h1>
              <p class="mt-2 max-w-md text-[0.78rem] leading-5 text-white/74 sm:text-[0.86rem]">
                {{ hero.subtitle }}
              </p>
            </div>
            <div class="shrink-0 rounded-[1rem] bg-[#facc15] px-2.5 py-1.5 text-right text-[0.62rem] font-black uppercase tracking-[0.16em] text-slate-950">
              <div>{{ hero.calloutBadge || t('Hot') }}</div>
              <div class="mt-0.5">{{ t('Now') }}</div>
            </div>
          </div>

          <div class="mt-3 grid grid-cols-2 gap-2">
            <article
              v-for="highlight in normalizedHighlights.slice(0, 2)"
              :key="highlight.title"
              class="rounded-[1rem] border border-white/10 bg-black/20 px-3 py-2.5"
            >
              <p class="text-[0.56rem] font-bold uppercase tracking-[0.18em] text-white/52">{{ highlight.eyebrow }}</p>
              <p class="mt-1 text-[0.78rem] font-bold leading-4 text-white">{{ highlight.title }}</p>
              <p class="mt-1 text-[0.68rem] leading-4 text-white/70">{{ highlight.subtitle }}</p>
            </article>
          </div>

          <div class="mt-3 grid grid-cols-2 gap-2">
            <component
              :is="primaryAction.isJump ? 'button' : Link"
              v-bind="primaryAction.bindings"
              class="inline-flex min-h-10 items-center justify-center rounded-full bg-[#ff6b35] px-4 text-[0.74rem] font-bold uppercase tracking-[0.12em] text-white shadow-[0_10px_24px_rgba(255,107,53,0.34)] transition hover:bg-[#ff5420] sm:min-h-11 sm:text-xs"
              @click="handleAction(primaryAction)"
            >
              {{ hero.primary?.label || t('Shop now') }}
            </component>
            <component
              :is="secondaryAction.isJump ? 'button' : Link"
              v-bind="secondaryAction.bindings"
              class="inline-flex min-h-10 items-center justify-center rounded-full border border-white/16 bg-white/8 px-4 text-[0.74rem] font-bold uppercase tracking-[0.12em] text-white transition hover:bg-white/12 sm:min-h-11 sm:text-xs"
              @click="handleAction(secondaryAction)"
            >
              {{ hero.secondary?.label || t('See trending') }}
            </component>
          </div>
        </div>

        <div class="grid grid-cols-[1.05fr_0.95fr] gap-2.5">
          <div class="overflow-hidden rounded-[1.2rem] border border-white/10 bg-white/8 p-1.5 backdrop-blur sm:rounded-[1.45rem]">
            <div class="relative h-full overflow-hidden rounded-[1rem] bg-[#1d1d1d] sm:rounded-[1.2rem]">
              <img
                v-if="hero.image"
                :src="hero.image"
                :alt="hero.title"
                class="aspect-[0.95] h-full w-full object-cover"
                loading="lazy"
              />
              <div v-else class="aspect-[0.95] h-full w-full bg-gradient-to-br from-[#2a2a2a] via-[#1a1a1a] to-[#101010]"></div>
              <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/75 via-black/28 to-transparent p-2.5">
                <p class="text-[0.56rem] font-bold uppercase tracking-[0.18em] text-white/55">{{ t('Simbazu picks') }}</p>
                <p class="mt-1 text-[0.74rem] font-bold leading-4 text-white">{{ hero.callout || t('Low-friction best sellers') }}</p>
              </div>
            </div>
          </div>

          <div class="grid gap-2.5">
            <article class="rounded-[1.1rem] bg-[#ff6b35] px-3 py-3 text-white">
              <p class="text-[0.54rem] font-bold uppercase tracking-[0.18em] text-white/72">{{ t('Flash drop') }}</p>
              <p class="mt-1 text-[1rem] font-black leading-none">{{ t('Up to 70% off') }}</p>
              <p class="mt-1 text-[0.68rem] leading-4 text-white/80">{{ t('Limited-time Simbazu picks') }}</p>
            </article>

            <article class="rounded-[1.1rem] border border-white/10 bg-white/8 px-3 py-3 backdrop-blur">
              <p class="text-[0.54rem] font-bold uppercase tracking-[0.18em] text-white/55">{{ t('Quick browse') }}</p>
              <div v-if="chips.length" class="mt-2 flex flex-wrap gap-1.5">
                <button
                  v-for="chip in chips.slice(0, 4)"
                  :key="chip.label"
                  type="button"
                  class="rounded-full border border-white/14 bg-black/18 px-2.5 py-1 text-[0.62rem] font-semibold text-white/86 transition hover:bg-black/24"
                  @click="$emit('jump', chip.target)"
                >
                  {{ chip.label }}
                </button>
              </div>
            </article>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-3 gap-2">
        <article
          v-for="stat in stats"
          :key="`footer-${stat.label}`"
          class="rounded-[1rem] border border-white/10 bg-white/7 px-2.5 py-2 text-center backdrop-blur"
        >
          <p class="text-[0.52rem] uppercase tracking-[0.18em] text-white/52">{{ stat.label }}</p>
          <p class="mt-1 text-[0.72rem] font-bold text-white sm:text-[0.8rem]">{{ stat.value }}</p>
        </article>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useTranslations } from '@/i18n'

const emit = defineEmits(['jump'])

const props = defineProps({
  hero: { type: Object, required: true },
  stats: { type: Array, default: () => [] },
  chips: { type: Array, default: () => [] },
  highlights: { type: Array, default: () => [] },
})

const { t } = useTranslations()

const normalizedHighlights = computed(() => {
  if (props.highlights?.length) return props.highlights

  return [
    { eyebrow: t('Fast delivery'), title: t('Low-friction checkout'), subtitle: t('Cart to payment without dead taps.') },
    { eyebrow: t('Trending'), title: t('High-velocity deals'), subtitle: t('Fresh promos and feed-first discovery.') },
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

const primaryAction = computed(() => createAction(props.hero?.primary, '/products'))
const secondaryAction = computed(() => createAction(props.hero?.secondary, '/promotions/flash-sales'))

const handleAction = (action) => {
  if (!action?.isJump) return
  emit('jump', action.href.replace(/^#/, ''))
}
</script>
