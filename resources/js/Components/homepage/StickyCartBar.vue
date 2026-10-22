<template>
  <Transition
    enter-active-class="transition duration-200 ease-out"
    enter-from-class="translate-y-4 opacity-0"
    enter-to-class="translate-y-0 opacity-100"
    leave-active-class="transition duration-150 ease-in"
    leave-from-class="translate-y-0 opacity-100"
    leave-to-class="translate-y-4 opacity-0"
  >
    <div
      v-if="cartCount > 0"
      class="pointer-events-none fixed inset-x-3 bottom-20 z-40 lg:bottom-6 lg:left-auto lg:right-6 lg:inset-x-auto"
    >
      <div class="pointer-events-auto flex min-w-[min(92vw,360px)] flex-col gap-3 rounded-[1.25rem] bg-[#111111] px-3.5 py-3 text-white shadow-[0_20px_40px_rgba(15,23,42,0.24)] ring-1 ring-white/10 sm:flex-row sm:items-center sm:justify-between sm:gap-3 sm:rounded-full sm:px-4">
        <div class="min-w-0">
          <p class="text-[0.62rem] font-bold uppercase tracking-[0.2em] text-white/55">{{ t('Cart ready') }}</p>
          <p class="truncate text-sm font-bold">
            {{ t(':count items · :total', { count: cartCount, total: formattedSubtotal }) }}
          </p>
        </div>
        <div class="grid shrink-0 grid-cols-2 gap-2 sm:flex">
          <Link href="/cart" class="inline-flex min-h-10 items-center justify-center rounded-full border border-white/16 px-3 text-[0.68rem] font-bold uppercase tracking-[0.1em] text-white/86 transition hover:bg-white/10 sm:px-4 sm:text-xs sm:tracking-[0.12em]">
            {{ t('View cart') }}
          </Link>
          <Link href="/checkout" class="inline-flex min-h-10 items-center justify-center rounded-full bg-[#ff6b35] px-3 text-[0.68rem] font-bold uppercase tracking-[0.1em] text-white transition hover:bg-[#ff5420] sm:px-4 sm:text-xs sm:tracking-[0.12em]">
            {{ t('Checkout') }}
          </Link>
        </div>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { useTranslations } from '@/i18n'
import { useUserPreferences } from '@/composables/useUserPreferences.js'

const { t } = useTranslations()
const page = usePage()
const { currentCurrency, convertCurrency, formatCurrency } = useUserPreferences()

const cartSummary = computed(() => page.props.cart ?? { lines: [], count: 0, subtotal: 0 })
const cartCount = computed(() => Number(cartSummary.value.count ?? 0))
const cartSubtotal = computed(() => Number(cartSummary.value.subtotal ?? 0))
const formattedSubtotal = computed(() =>
  formatCurrency(convertCurrency(cartSubtotal.value, 'USD', currentCurrency.value || 'USD'), currentCurrency.value || 'USD')
)
</script>
