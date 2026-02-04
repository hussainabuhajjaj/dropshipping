<template>
  <StorefrontLayout>
    <Head :title="campaign.name">
      <meta name="description" head-key="description" :content="campaign.hero_subtitle || campaign.name" />
    </Head>

    <section class="space-y-10">
      <div class="rounded-3xl border border-slate-100 bg-gradient-to-br from-slate-50 via-white to-amber-50 p-8">
        <div class="grid gap-8 lg:grid-cols-[1.2fr,0.8fr]">
          <div class="space-y-4">
            <p v-if="campaign.hero_kicker" class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
              {{ campaign.hero_kicker }}
            </p>
            <h1 class="text-3xl font-semibold text-slate-900">{{ campaign.name }}</h1>
            <p class="text-sm text-slate-600">{{ campaign.hero_subtitle }}</p>
            <div class="flex flex-wrap gap-2 text-xs text-slate-500">
              <span class="rounded-full bg-slate-100 px-3 py-1">{{ campaign.type }}</span>
              <span class="rounded-full bg-slate-100 px-3 py-1">{{ campaign.stacking_mode }}</span>
              <span v-if="campaign.starts_at" class="rounded-full bg-slate-100 px-3 py-1">
                {{ t('Starts') }} {{ formatDate(campaign.starts_at) }}
              </span>
              <span v-if="campaign.ends_at" class="rounded-full bg-slate-100 px-3 py-1">
                {{ t('Ends') }} {{ formatDate(campaign.ends_at) }}
              </span>
            </div>
          </div>
          <div class="overflow-hidden rounded-3xl border border-slate-100 bg-slate-100">
            <img v-if="campaign.hero_image" :src="campaign.hero_image" :alt="campaign.name" class="h-full w-full object-cover" />
          </div>
        </div>
      </div>

      <div v-if="campaign.content" class="prose max-w-none prose-slate" v-html="campaign.content"></div>

      <section v-if="collections.length" class="space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-semibold text-slate-900">{{ t('Collections in this campaign') }}</h2>
        </div>
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          <Link
            v-for="collection in collections"
            :key="collection.slug"
            :href="`/collections/${collection.slug}`"
            class="group rounded-3xl border border-slate-100 bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:shadow-lg"
          >
            <div class="aspect-[4/3] w-full overflow-hidden rounded-2xl bg-slate-100">
              <img v-if="collection.hero_image" :src="collection.hero_image" :alt="collection.title" class="h-full w-full object-cover" />
            </div>
            <div class="mt-4 space-y-2">
              <p v-if="collection.hero_kicker" class="text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-400">
                {{ collection.hero_kicker }}
              </p>
              <h3 class="text-lg font-semibold text-slate-900 group-hover:text-slate-700">
                {{ collection.title }}
              </h3>
              <p class="text-sm text-slate-600 line-clamp-3">{{ collection.description }}</p>
            </div>
          </Link>
        </div>
      </section>

      <section v-if="promotions.length" class="space-y-4">
        <h2 class="text-xl font-semibold text-slate-900">{{ t('Promotions') }}</h2>
        <div class="grid gap-4 md:grid-cols-2">
          <div v-for="promo in promotions" :key="promo.id" class="card p-5">
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                {{ promo.type }}
              </span>
              <span v-if="promo.end_at" class="text-xs text-amber-700 font-semibold">
                {{ t('Ends') }} {{ formatDate(promo.end_at) }}
              </span>
            </div>
            <h3 class="mt-2 text-lg font-semibold text-slate-900">{{ promo.name }}</h3>
            <p class="text-sm text-slate-600">{{ promo.description }}</p>
            <div class="mt-3 text-sm text-slate-700">
              <span class="font-medium">{{ t('Value') }}:</span>
              <span v-if="promo.value_type === 'percentage'">{{ promo.value }}%</span>
              <span v-else-if="promo.value_type === 'fixed'">{{ displayAmount(promo.value) }}</span>
              <span v-else>{{ t('Special') }}</span>
            </div>
          </div>
        </div>
      </section>

      <section v-if="coupons.length" class="space-y-4">
        <h2 class="text-xl font-semibold text-slate-900">{{ t('Coupons') }}</h2>
        <div class="grid gap-4 md:grid-cols-2">
          <div v-for="coupon in coupons" :key="coupon.id" class="card-muted p-5">
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Code') }}</span>
              <span class="text-sm font-semibold text-slate-900">{{ coupon.code }}</span>
            </div>
            <p class="mt-2 text-sm text-slate-600">{{ coupon.description }}</p>
            <div class="mt-3 text-sm text-slate-700">
              <span class="font-medium">{{ t('Discount') }}:</span>
              <span v-if="coupon.type === 'percentage'">{{ coupon.amount }}%</span>
              <span v-else>{{ displayAmount(coupon.amount) }}</span>
            </div>
          </div>
        </div>
      </section>

      <section v-if="banners.length" class="space-y-4">
        <h2 class="text-xl font-semibold text-slate-900">{{ t('Campaign banners') }}</h2>
        <div class="grid gap-4 md:grid-cols-2">
          <div v-for="banner in banners" :key="banner.id" class="overflow-hidden rounded-2xl border border-slate-100 bg-white">
            <img v-if="banner.imagePath" :src="banner.imagePath" :alt="banner.title" class="h-48 w-full object-cover" />
            <div class="p-4">
              <p class="text-sm font-semibold text-slate-900">{{ banner.title }}</p>
              <p class="text-xs text-slate-600">{{ banner.description }}</p>
              <Link v-if="banner.ctaUrl" :href="banner.ctaUrl" class="mt-3 inline-flex text-xs font-semibold text-slate-700">
                {{ banner.ctaText || t('Shop now') }} →
              </Link>
            </div>
          </div>
        </div>
      </section>
    </section>
  </StorefrontLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { useTranslations } from '@/i18n'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import { convertCurrency, formatCurrency } from '@/utils/currency.js'
import { useCurrency } from '@/composables/useCurrency.js'

const props = defineProps({
  campaign: { type: Object, required: true },
  promotions: { type: Array, default: () => [] },
  coupons: { type: Array, default: () => [] },
  banners: { type: Array, default: () => [] },
  collections: { type: Array, default: () => [] },
})

const { t, locale } = useTranslations()
const page = usePage()
const { selectedCurrency } = useCurrency()
const displayCurrency = computed(() => selectedCurrency.value || page.props.currency || 'USD')
const displayAmount = (amount) =>
  formatCurrency(convertCurrency(Number(amount ?? 0), 'USD', displayCurrency.value), displayCurrency.value)

const formatDate = (value) => {
  if (!value) return ''
  return new Date(value).toLocaleDateString(locale.value || 'en')
}
</script>
