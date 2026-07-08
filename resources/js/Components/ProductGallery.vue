<template>
  <div class="space-y-3">
    <div
      class="relative overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 touch-pan-y select-none cursor-grab active:cursor-grabbing"
      @pointerdown="onPointerDown"
      @pointerup="onPointerUp"
      @pointercancel="onPointerCancel"
    >
      <div
        v-if="selectedImage"
        class="flex items-center justify-center w-full aspect-[4/3] sm:aspect-square"
      >
        <img
          :src="selectedImage"
          :alt="imageAlt"
          class="max-h-full max-w-full object-contain"
          draggable="false"
          @dragstart.prevent
        />
      </div>
      <div
        v-else
        class="flex aspect-[4/3] items-center justify-center text-xs text-slate-400"
      >
        {{ t('Image coming soon') }}
      </div>

      <div
        v-if="images.length > 1"
        class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 md:hidden"
      >
        <div class="rounded-full bg-white/80 px-2 py-1 text-[10px] font-semibold text-slate-600 shadow-sm">
          {{ t('Swipe') }}
        </div>
      </div>

      <div
        v-if="images.length > 1"
        class="absolute inset-y-0 left-0 right-0 hidden items-center justify-between px-3 md:flex"
      >
        <button
          type="button"
          class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/90 text-slate-800 shadow-sm ring-1 ring-black/5 transition hover:bg-white"
          @click.stop="$emit('prev-image')"
        >
          <svg viewBox="0 0 20 20" class="h-5 w-5" fill="currentColor"><path fill-rule="evenodd" d="M12.78 15.53a.75.75 0 01-1.06 0l-5-5a.75.75 0 010-1.06l5-5a.75.75 0 111.06 1.06L8.31 10l4.47 4.47a.75.75 0 010 1.06z"/></svg>
        </button>
        <button
          type="button"
          class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/90 text-slate-800 shadow-sm ring-1 ring-black/5 transition hover:bg-white"
          @click.stop="$emit('next-image')"
        >
          <svg viewBox="0 0 20 20" class="h-5 w-5" fill="currentColor"><path fill-rule="evenodd" d="M7.22 4.47a.75.75 0 011.06 0l5 5a.75.75 0 010 1.06l-5 5a.75.75 0 11-1.06-1.06L11.69 10 7.22 5.53a.75.75 0 010-1.06z"/></svg>
        </button>
      </div>
    </div>

    <div v-if="images.length > 1" class="flex gap-2 overflow-x-auto pb-1 scrollbar-hide">
      <button
        v-for="(img, idx) in images"
        :key="idx"
        type="button"
        class="h-16 w-16 shrink-0 overflow-hidden rounded-lg border-2 transition"
        :class="img === selectedImage ? 'border-slate-900' : 'border-slate-200 hover:border-slate-400'"
        @click="$emit('select-image', img)"
      >
        <img :src="img" :alt="`${t('Image')} ${idx + 1}`" class="h-full w-full object-cover" />
      </button>
    </div>

    <div v-if="videos.length" class="space-y-3">
      <h2 class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">{{ t('Videos') }}</h2>
      <div class="grid gap-3">
        <video
          v-for="(video, idx) in videos"
          :key="idx"
          class="w-full rounded-xl border border-slate-200 bg-black/90"
          controls
          preload="metadata"
          playsinline
        >
          <source :src="video" />
        </video>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useTranslations } from '@/i18n'

const props = defineProps({
  images: { type: Array, default: () => [] },
  selectedImage: { type: String, default: null },
  imageAlt: { type: String, default: '' },
  videos: { type: Array, default: () => [] },
})

const emit = defineEmits(['select-image', 'prev-image', 'next-image'])

const { t } = useTranslations()

const pointerStartX = ref(0)
const pointerStartY = ref(0)
const pointerId = ref(null)

const onPointerDown = (event) => {
  if (props.images.length < 2) return
  if (!event.isPrimary) return

  pointerId.value = event.pointerId
  pointerStartX.value = event.clientX ?? 0
  pointerStartY.value = event.clientY ?? 0

  try {
    event.currentTarget?.setPointerCapture?.(event.pointerId)
  } catch {
    // ignore
  }
}

const onPointerUp = (event) => {
  if (props.images.length < 2) return
  if (pointerId.value !== null && event.pointerId !== pointerId.value) return

  const endX = event.clientX ?? 0
  const endY = event.clientY ?? 0
  const deltaX = endX - pointerStartX.value
  const deltaY = endY - pointerStartY.value

  pointerId.value = null

  if (Math.abs(deltaX) < 40 || Math.abs(deltaX) <= Math.abs(deltaY)) return

  if (deltaX < 0) {
    emit('next-image')
  } else {
    emit('prev-image')
  }
}

const onPointerCancel = () => {
  pointerId.value = null
}
</script>
