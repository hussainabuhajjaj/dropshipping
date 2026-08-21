<template>
  <form class="space-y-4" @submit.prevent="$emit('submit')">
    <div class="flex flex-wrap items-center gap-3">
      <div class="inline-flex items-center rounded-lg border border-slate-200 bg-white">
        <button
          type="button"
          class="flex h-10 w-10 items-center justify-center text-slate-600 transition hover:bg-slate-50 rounded-l-lg"
          @click="$emit('decrement-qty')"
        >
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M5 12h14"/>
          </svg>
        </button>
        <input
          :value="quantity"
          type="number"
          min="1"
          class="h-10 w-12 border-0 bg-transparent text-center text-sm font-semibold text-slate-900 focus:ring-0 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
          @input="$emit('update-qty', Number($event.target.value))"
        />
        <button
          type="button"
          class="flex h-10 w-10 items-center justify-center text-slate-600 transition hover:bg-slate-50 rounded-r-lg"
          @click="$emit('increment-qty')"
        >
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M12 5v14M5 12h14"/>
          </svg>
        </button>
      </div>

      <button
        type="submit"
        class="btn-red flex-1 min-w-[140px]"
        :disabled="disabled"
      >
        <div class="flex items-center justify-center gap-2">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/>
          </svg>
          <span>{{ label }}</span>
        </div>
      </button>

      <button
        type="button"
        class="inline-flex min-h-[2.75rem] items-center justify-center rounded-lg border border-[#25D366]/30 bg-[#25D366]/10 px-4 text-sm font-bold text-[#128C49] transition hover:bg-[#25D366]/15"
        :disabled="whatsAppBusy"
        @click="$emit('whatsapp')"
      >
        {{ whatsAppLabel }}
      </button>

      <ShareButton :product="product" />
    </div>

    <p
      v-if="successMessage"
      class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700"
    >
      {{ successMessage }}
    </p>

  </form>
</template>

<script setup>
import ShareButton from '@/Components/ShareButton.vue'

defineProps({
  product: { type: Object, required: true },
  disabled: { type: Boolean, default: false },
  label: { type: String, default: '' },
  whatsAppBusy: { type: Boolean, default: false },
  whatsAppLabel: { type: String, default: '' },
  successMessage: { type: String, default: '' },
  quantity: { type: Number, default: 1 },
})

defineEmits(['submit', 'decrement-qty', 'increment-qty', 'whatsapp', 'update-qty'])
</script>
