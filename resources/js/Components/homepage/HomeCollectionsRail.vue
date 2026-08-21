<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import HomeSectionHeader from '@/Components/homepage/HomeSectionHeader.vue'
import { useTranslations } from '@/i18n'

const props = defineProps({
  collections: { type: Array, default: () => [] },
  viewAllHref: { type: String, default: '/collections' },
})

const { t } = useTranslations()
const visibleCollections = computed(() => props.collections.slice(0, 12))
</script>

<template>
  <section v-if="visibleCollections.length">
    <HomeSectionHeader
      :eyebrow="t('Collections')"
      :title="t('Browse Collections')"
      :subtitle="t('Shop grouped picks for outfits, gifts, home, tech, and seasonal needs.')"
      :href="viewAllHref"
      :action-label="t('All collections')"
      icon="spark"
    />

    <div class="grid grid-flow-col auto-cols-[10rem] gap-2 overflow-x-auto pb-1 sm:auto-cols-[13rem] lg:grid-flow-row lg:grid-cols-5 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
      <Link
        v-for="collection in visibleCollections"
        :key="collection.id ?? collection.slug ?? collection.name ?? collection.title"
        :href="collection.href ?? `/collections/${collection.slug ?? collection.id}`"
        class="group relative min-h-44 overflow-hidden rounded-lg bg-slate-900 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md active:scale-[0.99] sm:min-h-52"
      >
        <img
          v-if="collection.image"
          :src="collection.image"
          :alt="collection.name || collection.title"
          class="absolute inset-0 h-full w-full object-cover transition duration-300 group-hover:scale-105"
          loading="lazy"
        />
        <div v-else class="absolute inset-0 bg-[#f6efe4]"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/78 via-slate-950/20 to-transparent"></div>
        <div class="absolute inset-x-0 bottom-0 p-3">
          <p class="text-[0.62rem] font-black uppercase tracking-[0.16em] text-[#fbbf24]">{{ t(collection.kicker || 'Curated') }}</p>
          <p class="mt-1 line-clamp-2 text-base font-black leading-tight text-white">{{ collection.name || collection.title }}</p>
        </div>
      </Link>
    </div>
  </section>
</template>
