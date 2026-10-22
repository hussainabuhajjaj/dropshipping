<template>
  <StorefrontLayout>
    <div class="space-y-5 pb-10 sm:space-y-8">
      <section class="rounded-[1.8rem] bg-[#111111] px-5 py-5 text-white shadow-[0_20px_48px_rgba(15,23,42,0.16)]">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="text-[0.68rem] font-bold uppercase tracking-[0.24em] text-[#facc15]">Account</p>
            <h1 class="mt-2 text-[1.95rem] font-black tracking-[-0.04em] sm:text-[2.2rem]">Wishlist</h1>
            <p class="mt-2 max-w-xl text-sm leading-6 text-white/72">Saved products should feel like a second feed, ready to convert when price, mood, or stock lines up.</p>
          </div>
          <Link href="/products" class="inline-flex min-h-11 items-center justify-center rounded-full border border-white/15 bg-white/8 px-4 text-sm font-semibold text-white transition hover:bg-white/12">Continue shopping</Link>
        </div>
      </section>

      <div class="flex gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        <div class="shrink-0 rounded-full bg-[#fff4e8] px-3 py-2 text-[0.7rem] font-bold uppercase tracking-[0.16em] text-[#c55b24]">{{ products.length }} saved items</div>
        <div class="shrink-0 rounded-full bg-white px-3 py-2 text-[0.7rem] font-bold uppercase tracking-[0.16em] text-slate-500 ring-1 ring-[#eadfce]">Revisit best finds</div>
        <div class="shrink-0 rounded-full bg-white px-3 py-2 text-[0.7rem] font-bold uppercase tracking-[0.16em] text-slate-500 ring-1 ring-[#eadfce]">Tap to quick add</div>
      </div>

      <div v-if="products.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <div v-for="product in products" :key="product.id" class="relative">
          <ProductCard :product="product" :currency="currency" />
          <div class="absolute right-3 top-3">
            <Link
              :href="route('account.wishlist.destroy', product.id)"
              method="delete"
              as="button"
              class="rounded-full border border-slate-200 bg-white/90 px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-white"
            >
              Remove
            </Link>
          </div>
        </div>
      </div>

      <EmptyState
        v-else
        variant="compact"
        eyebrow="Wishlist"
        title="Nothing saved yet"
        message="Tap the heart icon on a product to save it for later."
      >
        <template #actions>
          <Link href="/products" class="btn-primary text-xs">Browse products</Link>
        </template>
      </EmptyState>
    </div>
  </StorefrontLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import ProductCard from '@/Components/ProductCard.vue'
import EmptyState from '@/Components/EmptyState.vue'

const props = defineProps({
  products: { type: Array, default: () => [] },
  currency: { type: String, default: 'USD' },
})

</script>
