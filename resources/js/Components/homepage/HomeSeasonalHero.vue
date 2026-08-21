<script setup>
import { computed, shallowRef } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useTranslations } from '@/i18n'

const props = defineProps({
  season: { type: Object, required: true },
  slides: { type: Array, default: () => [] },
  quickLinks: { type: Array, default: () => [] },
  collections: { type: Array, default: () => [] },
})

const { t } = useTranslations()
const activeIndex = shallowRef(0)

const displaySlides = computed(() => {
  if (!props.slides.length) {
    return [{
      key: 'season-default',
      badge: props.season.badge,
      title: props.season.title,
      subtitle: props.season.subtitle,
      image: null,
      primary: { label: props.season.primaryLabel, href: props.season.href },
    }]
  }

  return props.slides.map((slide, index) => ({
    key: slide.key || `slide-${index}`,
    badge: slide.badge || props.season.badge,
    title: slide.title || props.season.title,
    subtitle: slide.subtitle || props.season.subtitle,
    image: slide.image || null,
    primary: slide.primary || { label: props.season.primaryLabel, href: props.season.href },
  }))
})

const currentSlide = computed(() => displaySlides.value[activeIndex.value] || displaySlides.value[0])
const heroStyle = computed(() => (
  currentSlide.value?.image
    ? { backgroundImage: `url(${currentSlide.value.image})` }
    : {}
))

const themeClasses = computed(() => {
  const themes = {
    amber: {
      background: 'bg-[#f6efe4]',
      badge: 'bg-[#fff4d8] text-[#9a5b00] ring-[#f3d38a]',
      cta: 'bg-[#f59e0b] text-slate-950 hover:bg-[#d97706]',
      accent: 'text-[#d97706]',
    },
    blue: {
      background: 'bg-[#edf4fb]',
      badge: 'bg-[#e3f0ff] text-[#155e9f] ring-[#bad8f5]',
      cta: 'bg-[#0f5f93] text-white hover:bg-[#0b4a73]',
      accent: 'text-[#0f5f93]',
    },
    emerald: {
      background: 'bg-[#edf7ee]',
      badge: 'bg-[#ddf7e6] text-[#17633a] ring-[#bde8ca]',
      cta: 'bg-[#177245] text-white hover:bg-[#105a35]',
      accent: 'text-[#177245]',
    },
    rose: {
      background: 'bg-[#fbefef]',
      badge: 'bg-[#ffe7e7] text-[#9f3535] ring-[#f1c7c7]',
      cta: 'bg-[#b23b45] text-white hover:bg-[#922f38]',
      accent: 'text-[#b23b45]',
    },
  }

  return themes[props.season.theme] || themes.amber
})

const sideCollections = computed(() => {
  const source = props.collections.length ? props.collections : props.quickLinks

  return source.slice(0, 3).map((item) => ({
    ...item,
    title: item.title || item.name,
    name: item.name || item.title,
    href: item.href || '/collections',
    kicker: item.kicker || 'Collection',
  }))
})
const visibleQuickLinks = computed(() => props.quickLinks.slice(0, 6))

const goToSlide = (index) => {
  activeIndex.value = index
}

const nextSlide = () => {
  activeIndex.value = (activeIndex.value + 1) % displaySlides.value.length
}

const prevSlide = () => {
  activeIndex.value = (activeIndex.value - 1 + displaySlides.value.length) % displaySlides.value.length
}
</script>

<template>
  <section class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_22rem]">
    <div
      class="relative min-h-[31rem] overflow-hidden rounded-lg bg-slate-950 bg-cover bg-center text-white shadow-sm sm:min-h-[29rem]"
      :style="heroStyle"
    >
      <div class="absolute inset-0 bg-gradient-to-r from-slate-950/92 via-slate-950/62 to-slate-950/10"></div>

      <div class="relative z-10 flex min-h-[31rem] flex-col justify-between p-4 sm:min-h-[29rem] sm:p-6 lg:p-8">
        <div class="flex flex-wrap items-center gap-2">
          <span
            class="inline-flex rounded-full px-3 py-1 text-[0.66rem] font-black uppercase tracking-[0.18em] ring-1"
            :class="themeClasses.badge"
          >
            {{ t(currentSlide.badge) }}
          </span>
          <span class="inline-flex rounded-full bg-white/12 px-3 py-1 text-[0.66rem] font-bold uppercase tracking-[0.16em] text-white/80 ring-1 ring-white/15">
            {{ t('New drops daily') }}
          </span>
        </div>

        <div class="max-w-2xl py-8">
          <h1 class="max-w-xl text-3xl font-black leading-tight text-white sm:text-5xl lg:text-6xl">
            {{ t(currentSlide.title) }}
          </h1>
          <p class="mt-4 max-w-xl text-sm font-medium leading-6 text-white/78 sm:text-base">
            {{ t(currentSlide.subtitle) }}
          </p>

          <div class="mt-6 flex flex-wrap gap-2">
            <Link
              :href="currentSlide.primary?.href || season.href"
              class="inline-flex min-h-11 items-center justify-center rounded-md px-5 text-sm font-black transition active:scale-95"
              :class="themeClasses.cta"
            >
              {{ t(currentSlide.primary?.label || season.primaryLabel) }}
            </Link>
            <Link
              href="/products?sort=bestsellers"
              class="inline-flex min-h-11 items-center justify-center rounded-md bg-white/12 px-5 text-sm font-bold text-white ring-1 ring-white/16 transition hover:bg-white/18 active:scale-95"
            >
              {{ t(season.secondaryLabel) }}
            </Link>
          </div>
        </div>

        <div class="grid gap-2 sm:grid-cols-3">
          <Link
            v-for="link in visibleQuickLinks"
            :key="link.href"
            :href="link.href"
            class="group rounded-lg bg-white/92 p-3 text-slate-950 shadow-sm transition hover:bg-white active:scale-[0.99]"
          >
            <p class="text-[0.62rem] font-black uppercase tracking-[0.16em]" :class="themeClasses.accent">
              {{ t('Shop') }}
            </p>
            <p class="mt-1 line-clamp-1 text-sm font-black">{{ link.name }}</p>
            <p class="mt-1 text-xs font-medium text-slate-500">{{ t('Open collection') }}</p>
          </Link>
        </div>

        <div v-if="displaySlides.length > 1" class="absolute bottom-4 right-4 flex gap-2">
          <button
            type="button"
            class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/92 text-slate-900 shadow-sm transition hover:bg-white active:scale-95"
            :aria-label="t('Previous slide')"
            @click="prevSlide"
          >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6" />
            </svg>
          </button>
          <button
            type="button"
            class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/92 text-slate-900 shadow-sm transition hover:bg-white active:scale-95"
            :aria-label="t('Next slide')"
            @click="nextSlide"
          >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6" />
            </svg>
          </button>
        </div>
      </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
      <Link
        v-for="collection in sideCollections"
        :key="collection.id ?? collection.href ?? collection.title ?? collection.name"
        :href="collection.href ?? '/collections'"
        class="group relative min-h-40 overflow-hidden rounded-lg bg-slate-900 shadow-sm"
      >
        <img
          v-if="collection.image"
          :src="collection.image"
          :alt="collection.title || collection.name"
          class="absolute inset-0 h-full w-full object-cover transition duration-300 group-hover:scale-105"
          loading="lazy"
        />
        <div v-else class="absolute inset-0" :class="themeClasses.background"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/78 via-slate-950/28 to-transparent"></div>
        <div class="absolute inset-x-0 bottom-0 p-4">
          <p class="text-[0.62rem] font-black uppercase tracking-[0.18em] text-[#fbbf24]">
            {{ t(collection.kicker || 'Curated') }}
          </p>
          <p class="mt-1 line-clamp-2 text-lg font-black leading-tight text-white">
            {{ collection.title || collection.name }}
          </p>
        </div>
      </Link>
    </div>
  </section>
</template>
