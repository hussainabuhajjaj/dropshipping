<template>
  <div class="fixed inset-x-0 bottom-0 z-[120] border-t border-slate-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden">
    <div class="container-base py-2">
      <div class="flex items-center gap-2 px-1">
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2">
            <p class="truncate text-sm font-bold text-slate-900">{{ title }}</p>
            <span
              v-if="stockLabel"
              class="shrink-0 text-[0.5rem] font-semibold uppercase tracking-wider"
              :class="inStock ? 'text-emerald-600' : 'text-red-500'"
            >
              {{ stockLabel }}
            </span>
          </div>
          <div class="mt-0.5 flex items-center gap-2">
            <span class="text-base font-black text-slate-900">{{ price }}</span>
            <span v-if="compareAt" class="text-xs text-slate-400 line-through">{{ compareAt }}</span>
            <span v-if="rating" class="flex items-center gap-1 text-[0.55rem] text-slate-500">
              <svg class="h-3 w-3 text-amber-400" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3.5l2.6 5.4 6 .9-4.3 4.1 1 5.8L12 16.9 6.7 19.7l1-5.8-4.3-4.1 6-.9L12 3.5z"/></svg>
              {{ rating }}
            </span>
          </div>
        </div>

        <div class="flex shrink-0 gap-1.5">
          <button
            type="button"
            class="flex min-h-11 items-center gap-2 rounded-full bg-[#25D366] px-4 text-sm font-bold text-white shadow-[0_4px_16px_rgba(37,211,102,0.3)] transition hover:bg-[#20BD5E] active:scale-95"
            @click="$emit('whatsapp')"
          >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
              <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>
          </button>

          <button
            type="button"
            class="btn-primary flex min-h-11 items-center gap-1.5 px-4 text-sm font-bold"
            :disabled="disabled"
            @click="$emit('submit')"
          >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/>
            </svg>
            <span>{{ ctaLabel }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  price: { type: String, required: true },
  compareAt: { type: String, default: '' },
  ctaLabel: { type: String, required: true },
  disabled: { type: Boolean, default: false },
  rating: { type: [String, Number], default: null },
  stockLabel: { type: String, default: '' },
  inStock: { type: Boolean, default: true },
})

defineEmits(['submit', 'whatsapp'])
</script>
