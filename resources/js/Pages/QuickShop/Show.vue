<script setup>
import { computed } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import Breadcrumbs from '@/Components/Breadcrumbs.vue'
import EmptyState from '@/Components/EmptyState.vue'
import ProductCard from '@/Components/ProductCard.vue'
import { useTranslations } from '@/i18n'

const props = defineProps({
  lane: { type: Object, required: true },
  lanes: { type: Array, default: () => [] },
  products: { type: Object, required: true },
  currency: { type: String, default: 'USD' },
  promotions: { type: Array, default: () => [] },
  breadcrumbs: { type: Array, default: () => [] },
})

const page = usePage()
const { t } = useTranslations()

const productsPaginator = computed(() => props.products ?? { data: [] })
const products = computed(() => productsPaginator.value.data ?? [])
const promotionList = computed(() => props.promotions.length ? props.promotions : (page?.props?.homepagePromotions || []))

const isSaleLane = computed(() => props.lane?.tone === 'sale')
const hasMore = computed(() => (productsPaginator.value.current_page ?? 1) < (productsPaginator.value.last_page ?? 1))

const pageNumbers = computed(() => {
  const current = productsPaginator.value.current_page ?? 1
  const last = productsPaginator.value.last_page ?? 1

  if (last <= 7) return Array.from({ length: last }, (_, index) => index + 1)

  const pages = [1]
  if (current > 3) pages.push('...')

  const start = Math.max(2, current - 1)
  const end = Math.min(last - 1, current + 1)
  for (let page = start; page <= end; page += 1) pages.push(page)

  if (current < last - 2) pages.push('...')
  pages.push(last)

  return pages
})

const goToPage = (targetPage) => {
  const last = productsPaginator.value.last_page ?? 1
  if (targetPage < 1 || targetPage > last) return

  router.get(window.location.pathname, { page: targetPage }, {
    preserveScroll: true,
    preserveState: true,
  })
}
</script>

<template>
  <StorefrontLayout>
    <Head>
      <title>{{ t(lane.title) }}</title>
      <meta name="robots" content="index, follow" head-key="robots" />
      <link v-if="productsPaginator.prev_page_url" rel="prev" :href="productsPaginator.prev_page_url" />
      <link v-if="productsPaginator.next_page_url" rel="next" :href="productsPaginator.next_page_url" />
    </Head>

    <main class="min-h-screen bg-[#f7f4ef] pb-28">
      <Breadcrumbs v-if="breadcrumbs.length" :items="breadcrumbs" class="px-4" />

      <div class="mx-auto max-w-7xl space-y-6 px-4 py-5 sm:py-7">
        <section
          class="overflow-hidden rounded-lg shadow-sm"
          :class="isSaleLane ? 'bg-slate-950 text-white' : 'bg-white text-slate-950 ring-1 ring-[#eee6da]'"
        >
          <div class="grid gap-4 p-4 sm:p-6 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-end">
            <div>
              <p
                class="text-[0.68rem] font-black uppercase tracking-[0.2em]"
                :class="isSaleLane ? 'text-[#fbbf24]' : 'text-[#d97706]'"
              >
                {{ t(lane.eyebrow) }}
              </p>
              <h1 class="mt-2 text-3xl font-black leading-tight sm:text-5xl">
                {{ t(lane.title) }}
              </h1>
              <p
                class="mt-3 max-w-2xl text-sm font-medium leading-6 sm:text-base"
                :class="isSaleLane ? 'text-white/70' : 'text-slate-600'"
              >
                {{ t(lane.subtitle) }}
              </p>
            </div>

            <div class="grid grid-cols-2 gap-2">
              <div
                class="rounded-lg p-3"
                :class="isSaleLane ? 'bg-white/10' : 'bg-[#faf7f2]'"
              >
                <p class="text-[0.62rem] font-black uppercase tracking-[0.16em]" :class="isSaleLane ? 'text-white/45' : 'text-slate-400'">
                  {{ t('Products') }}
                </p>
                <p class="mt-1 text-xl font-black">{{ productsPaginator.total ?? products.length }}</p>
              </div>
              <Link
                href="/"
                class="rounded-lg p-3 transition active:scale-[0.99]"
                :class="isSaleLane ? 'bg-[#f59e0b] text-slate-950 hover:bg-[#d97706]' : 'bg-slate-950 text-white hover:bg-slate-800'"
              >
                <p class="text-[0.62rem] font-black uppercase tracking-[0.16em] opacity-70">{{ t('Home') }}</p>
                <p class="mt-1 text-sm font-black">{{ t('Back to shop') }}</p>
              </Link>
            </div>
          </div>
        </section>

        <section class="rounded-lg border border-[#eee6da] bg-white p-3 shadow-sm">
          <div class="flex gap-2 overflow-x-auto [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            <Link
              v-for="item in lanes"
              :key="item.key"
              :href="item.href"
              class="shrink-0 rounded-full border px-3.5 py-2 text-xs font-black transition"
              :class="item.key === lane.key
                ? 'border-slate-950 bg-slate-950 text-white'
                : item.tone === 'sale'
                  ? 'border-[#f59e0b] bg-[#fff4df] text-[#9a5b00] hover:bg-[#ffe8ba]'
                  : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50 hover:text-slate-950'"
            >
              {{ t(item.label) }}
            </Link>
          </div>
        </section>

        <section v-if="products.length">
          <div class="mb-3 flex items-center justify-between gap-4">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400">
              {{ productsPaginator.total ?? products.length }} {{ t('products') }}
            </p>
            <Link href="/products" class="text-xs font-black text-[#d97706] transition hover:text-slate-950">
              {{ t('All products') }}
            </Link>
          </div>

          <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            <ProductCard
              v-for="product in products"
              :key="product.id"
              :product="product"
              :currency="currency"
              :promotions="promotionList"
            />
          </div>

          <nav
            v-if="hasMore || (productsPaginator.current_page ?? 1) > 1"
            class="mt-8 flex items-center justify-center gap-1"
          >
            <button
              type="button"
              class="flex h-9 w-9 items-center justify-center rounded-lg text-sm text-slate-500 transition hover:bg-white disabled:pointer-events-none disabled:opacity-30"
              :disabled="(productsPaginator.current_page ?? 1) <= 1"
              :aria-label="t('Previous page')"
              @click="goToPage((productsPaginator.current_page ?? 1) - 1)"
            >
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6" />
              </svg>
            </button>

            <template v-for="(pageNumber, index) in pageNumbers" :key="`${pageNumber}-${index}`">
              <span v-if="pageNumber === '...'" class="px-1 text-xs text-slate-400">...</span>
              <button
                v-else
                type="button"
                class="flex h-9 min-w-9 items-center justify-center rounded-lg px-2 text-xs font-bold transition"
                :class="pageNumber === (productsPaginator.current_page ?? 1) ? 'bg-slate-950 text-white' : 'bg-white text-slate-600 hover:bg-slate-100'"
                @click="goToPage(pageNumber)"
              >
                {{ pageNumber }}
              </button>
            </template>

            <button
              type="button"
              class="flex h-9 w-9 items-center justify-center rounded-lg text-sm text-slate-500 transition hover:bg-white disabled:pointer-events-none disabled:opacity-30"
              :disabled="(productsPaginator.current_page ?? 1) >= (productsPaginator.last_page ?? 1)"
              :aria-label="t('Next page')"
              @click="goToPage((productsPaginator.current_page ?? 1) + 1)"
            >
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6" />
              </svg>
            </button>
          </nav>
        </section>

        <EmptyState
          v-else
          :eyebrow="t(lane.eyebrow)"
          :title="t('No products in this lane yet')"
          :message="t('Try another quick shop lane or browse the full catalog.')"
        >
          <template #actions>
            <Link href="/products" class="btn-primary">{{ t('All products') }}</Link>
            <Link href="/" class="btn-ghost">{{ t('Back home') }}</Link>
          </template>
        </EmptyState>
      </div>
    </main>
  </StorefrontLayout>
</template>
