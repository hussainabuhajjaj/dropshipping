<script setup>
import Modal from '@/Components/Modal.vue'

const props = defineProps({
  show: { type: Boolean, default: false },
  title: { type: String, default: 'You must log in first to continue shopping.' },
  message: { type: String, default: 'Log in to add this item to your cart and continue checkout.' },
})

const emit = defineEmits(['close', 'login'])
</script>

<template>
  <Modal :show="show" maxWidth="sm" @close="emit('close')">
    <div class="p-6 space-y-4 bg-white">
      <div class="flex items-center gap-3">
        <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-amber-100 text-amber-600">
          <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 12c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm0 0v6m-7 0h14" />
          </svg>
        </span>
        <div class="space-y-1">
          <h3 class="text-lg font-semibold text-slate-900">{{ title }}</h3>
          <p class="text-sm text-slate-600">{{ message }}</p>
        </div>
      </div>
      <div class="flex justify-end gap-3">
        <button
          type="button"
          class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
          @click="emit('close')"
        >
          Maybe later
        </button>
        <button
          type="button"
          class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-amber-600"
          @click="emit('login')"
        >
          Go to login
        </button>
      </div>
    </div>
  </Modal>
</template>
