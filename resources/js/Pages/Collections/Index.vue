<template>
  <StorefrontLayout>
    <section class="space-y-10">
      <header class="space-y-2">
        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">{{ t('Collections') }}</p>
        <h1 class="text-3xl font-semibold text-slate-900">{{ t('Seasonal drops & curated collections') }}</h1>
        <p class="text-sm text-slate-600">
          {{ t('Browse themed edits, seasonal drops, and buyer guides curated by our team.') }}
        </p>
      </header>

      <div v-if="hasCollections" class="space-y-12">
        <section
          v-for="(items, type) in collections"
          :key="type"
          class="space-y-4"
        >
          <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ typeLabel(type) }}</p>
              <h2 class="text-2xl font-semibold text-slate-900">{{ typeHeading(type) }}</h2>
            </div>
          </div>

          <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <Link
              v-for="collection in items"
              :key="collection.slug"
              :href="`/collections/${collection.slug}`"
              class="group rounded-3xl border border-slate-100 bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:shadow-lg"
            >
              <div class="aspect-[4/3] w-full overflow-hidden rounded-2xl bg-slate-100">
                <img
                  v-if="collection.hero_image"
                  :src="collection.hero_image"
                  :alt="collection.title"
                  class="h-full w-full object-cover"
                />
              </div>
              <div class="mt-4 space-y-2">
                <p v-if="collection.hero_kicker" class="text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-400">
                  {{ collection.hero_kicker }}
                </p>
                <h3 class="text-lg font-semibold text-slate-900 group-hover:text-slate-700">
                  {{ collection.title }}
                </h3>
                <p class="text-sm text-slate-600 line-clamp-3">
                  {{ collection.description || t('Discover curated picks tailored for this moment.') }}
                </p>
              </div>
              <div class="mt-4 inline-flex items-center text-xs font-semibold text-slate-700">
                {{ t('Explore collection') }}
                <span class="ml-2">→</span>
              </div>
            </Link>
          </div>
        </section>
      </div>

      <div v-else class="card-muted p-6 text-sm text-slate-600">
        {{ t('Collections are being curated. Check back soon for seasonal drops and guides.') }}
      </div>
    </section>
  </StorefrontLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import { useTranslations } from '@/i18n'

const props = defineProps({
  collections: { type: Object, default: () => ({}) },
})

const { t } = useTranslations()

const hasCollections = computed(() => Object.keys(props.collections || {}).length > 0)

const typeLabel = (type) => {
  switch (type) {
    case 'guide':
      return t('Guides')
    case 'seasonal':
      return t('Seasonal')
    case 'drop':
      return t('Drops')
    default:
      return t('Collection')
  }
}

const typeHeading = (type) => {
  switch (type) {
    case 'guide':
      return t('Buying guides')
    case 'seasonal':
      return t('Seasonal drops')
    case 'drop':
      return t('Limited drops')
    default:
      return t('Curated collections')
  }
}
</script>
