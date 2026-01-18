<template>
  <StorefrontLayout>
    <div class="max-w-5xl mx-auto py-10 space-y-8">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold">{{ t('Deals') }}</h1>
          <p class="text-slate-500 text-sm">{{ t('Automatic discounts applied on qualifying orders.') }}</p>
        </div>
        <Link href="/promotions/flash-sales" class="text-sm text-[#29ab87] font-semibold">
          {{ t('View flash sales') }}
        </Link>
      </div>

      <div v-if="promotions.length" class="grid gap-4 md:grid-cols-2">
        <div v-for="promo in promotions" :key="promo.id" class="card p-5">
          <div class="flex items-center justify-between">
            <span class="badge-accent">{{ promo.badge_text || promo.name }}</span>
            <span v-if="promoCountdown(promo)" class="text-xs text-amber-700 font-semibold">
              {{ t('Ends in :time', { time: promoCountdown(promo) }) }}
            </span>
          </div>
          <h2 class="text-lg font-semibold mt-3">{{ promo.name }}</h2>
          <p class="text-sm text-slate-600 mt-1">{{ promo.description }}</p>
          <div class="mt-3 text-sm text-slate-700">
            <span class="font-medium">{{ t('Value') }}:</span>
            <span v-if="promo.value_type === 'percentage'">{{ promo.value }}%</span>
            <span v-else-if="promo.value_type === 'fixed'">{{ displayPrice(promo.value) }}</span>
            <span v-else>{{ t('Special') }}</span>
          </div>
          <p v-if="promo.apply_hint" class="text-xs text-slate-500 mt-2">{{ promo.apply_hint }}</p>
          <Link href="/promotions/products" class="inline-flex text-xs font-semibold text-[#29ab87] mt-3">
            {{ t('Shop promoted products') }}
          </Link>
        </div>
      </div>
      <div v-else class="text-center text-slate-500 py-10">{{ t('No active deals right now.') }}</div>
    </div>
  </StorefrontLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import { useTranslations } from '@/i18n'
import { usePromoNow, formatCountdown } from '@/composables/usePromoCountdown.js'
import { formatCurrency } from '@/utils/currency.js'

const props = defineProps({
  promotions: { type: Array, default: () => [] },
  currency: { type: String, default: 'USD' },
})

const { t } = useTranslations()
const now = usePromoNow()
const promoCountdown = (promo) => formatCountdown(promo?.end_at, now.value)
const displayPrice = (amount) => formatCurrency(Number(amount ?? 0), props.currency)
</script>
