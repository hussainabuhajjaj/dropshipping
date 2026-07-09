<template>
  <div class="space-y-3">
    <div
      ref="imageContainer"
      class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 touch-pan-y select-none cursor-grab active:cursor-grabbing"
      @pointerdown="onPointerDown"
      @pointerup="onPointerUp"
      @pointercancel="onPointerCancel"
    >
      <div
        v-if="selectedImage"
        class="relative w-full"
        style="aspect-ratio: 3 / 4;"
        @mousemove="onMouseMove"
        @mouseleave="onMouseLeave"
        @click="openLightbox"
      >
        <img
          :src="selectedImage"
          :alt="imageAlt"
          class="h-full w-full object-cover"
          draggable="false"
          @dragstart.prevent
        />

        <div
          v-if="isZoomVisible && !isDragging"
          class="pointer-events-none absolute inset-0"
        >
          <div
            class="hidden lg:block absolute border-2 border-slate-400/60 bg-white/10 rounded-full"
            :style="{
              width: zoomSize + 'px',
              height: zoomSize + 'px',
              left: zoomPos.x - zoomSize / 2 + 'px',
              top: zoomPos.y - zoomSize / 2 + 'px',
            }"
          />
        </div>
      </div>
      <div
        v-else
        class="flex items-center justify-center text-xs text-slate-400"
        style="aspect-ratio: 3 / 4;"
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
        class="absolute right-2 top-2 z-10 flex gap-1.5 opacity-0 transition-opacity group-hover:opacity-100"
      >
        <button
          type="button"
          class="flex h-8 w-8 items-center justify-center rounded-full bg-white/90 text-slate-700 shadow-sm backdrop-blur transition hover:bg-white"
          :aria-label="t('Zoom in')"
          @click.stop="openLightbox"
        >
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607zM10.5 7.5v6m3-3h-6" />
          </svg>
        </button>
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
        class="relative h-16 w-16 shrink-0 overflow-hidden rounded-lg border-2 transition"
        :class="img === selectedImage ? 'border-slate-900' : 'border-slate-200 hover:border-slate-400'"
        @click="selectThumbnail(img)"
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

    <Teleport to="body">
      <Transition
        enter-active-class="transition-opacity duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="lightboxOpen"
          class="fixed inset-0 z-[200] flex items-center justify-center bg-black/95 backdrop-blur-sm"
          @click="closeLightbox"
        >
          <button
            type="button"
            class="absolute right-4 top-4 z-10 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
            @click="closeLightbox"
          >
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>

          <button
            v-if="images.length > 1"
            type="button"
            class="absolute left-4 top-1/2 z-10 flex h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
            @click.stop="prevLightbox"
          >
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
          </button>

          <img
            :src="lightboxImage"
            :alt="imageAlt"
            class="max-h-[90vh] max-w-[90vw] object-contain"
            @click.stop
          />

          <button
            v-if="images.length > 1"
            type="button"
            class="absolute right-4 top-1/2 z-10 flex h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
            @click.stop="nextLightbox"
          >
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
          </button>

          <div class="absolute bottom-6 left-1/2 z-10 -translate-x-1/2 text-xs text-white/60">
            {{ lightboxIndex + 1 }} / {{ images.length }}
          </div>
        </div>
      </Transition>
    </Teleport>

    <Teleport to="body">
      <div
        v-if="isZoomVisible && zoomImage && !isDragging"
        class="pointer-events-none fixed z-[190] hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl lg:block"
        :style="{
          width: zoomLensSize + 'px',
          height: zoomLensSize + 'px',
          left: zoomLensPos.x + 'px',
          top: zoomLensPos.y + 'px',
        }"
      >
        <div
          class="h-full w-full"
          :style="{
            backgroundImage: `url(${zoomImage})`,
            backgroundSize: zoomBackgroundSize,
            backgroundPosition: zoomBackgroundPos,
            backgroundRepeat: 'no-repeat',
          }"
        />
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useTranslations } from '@/i18n'

const props = defineProps({
  images: { type: Array, default: () => [] },
  selectedImage: { type: String, default: null },
  imageAlt: { type: String, default: '' },
  videos: { type: Array, default: () => [] },
})

const emit = defineEmits(['select-image', 'prev-image', 'next-image'])

const { t } = useTranslations()

const imageContainer = ref(null)
const isDragging = ref(false)

const pointerStartX = ref(0)
const pointerStartY = ref(0)
const pointerId = ref(null)

// Zoom
const isZoomVisible = ref(false)
const zoomPos = ref({ x: 0, y: 0 })
const zoomSize = 120
const zoomScale = 2.5

const zoomImage = computed(() => props.selectedImage)

const zoomLensSize = computed(() => 360)

const zoomLensPos = computed(() => {
  if (!imageContainer.value) return { x: 0, y: 0 }
  const rect = imageContainer.value.getBoundingClientRect()
  const x = zoomPos.value.x
  const y = zoomPos.value.y
  const lensHalf = zoomLensSize.value / 2
  let lensX = rect.left + x - lensHalf
  let lensY = rect.top + y - lensHalf
  lensX = Math.max(10, Math.min(lensX, window.innerWidth - zoomLensSize.value - 10))
  lensY = Math.max(10, Math.min(lensY, window.innerHeight - zoomLensSize.value - 10))
  return { x: lensX, y: lensY }
})

const zoomBackgroundSize = computed(() => `${zoomScale * 100}%`)

const zoomBackgroundPos = computed(() => {
  if (!imageContainer.value) return '0 0'
  const rect = imageContainer.value.getBoundingClientRect()
  const xPct = (zoomPos.value.x / rect.width) * 100
  const yPct = (zoomPos.value.y / rect.height) * 100
  return `${xPct}% ${yPct}%`
})

const onMouseMove = (event) => {
  if (isDragging.value) return
  if (!imageContainer.value) return
  const rect = imageContainer.value.getBoundingClientRect()
  const x = event.clientX - rect.left
  const y = event.clientY - rect.top
  zoomPos.value = { x, y }
  isZoomVisible.value = true
}

const onMouseLeave = () => {
  isZoomVisible.value = false
}

// Lightbox
const lightboxOpen = ref(false)
const lightboxIndex = ref(0)

const lightboxImage = computed(() => {
  if (props.images.length === 0) return props.selectedImage
  return props.images[lightboxIndex.value] || props.selectedImage
})

const openLightbox = () => {
  if (!props.selectedImage) return
  lightboxIndex.value = props.images.indexOf(props.selectedImage)
  if (lightboxIndex.value === -1) lightboxIndex.value = 0
  lightboxOpen.value = true
  document.body.style.overflow = 'hidden'
}

const closeLightbox = () => {
  lightboxOpen.value = false
  document.body.style.overflow = ''
}

const prevLightbox = () => {
  if (props.images.length < 2) return
  lightboxIndex.value = (lightboxIndex.value - 1 + props.images.length) % props.images.length
}

const nextLightbox = () => {
  if (props.images.length < 2) return
  lightboxIndex.value = (lightboxIndex.value + 1) % props.images.length
}

const selectThumbnail = (img) => {
  emit('select-image', img)
}

const onPointerDown = (event) => {
  if (props.images.length < 2) return
  if (!event.isPrimary) return
  isDragging.value = true

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
  isDragging.value = false

  if (Math.abs(deltaX) < 40 || Math.abs(deltaX) <= Math.abs(deltaY)) return

  if (deltaX < 0) {
    emit('next-image')
  } else {
    emit('prev-image')
  }
}

const onPointerCancel = () => {
  pointerId.value = null
  isDragging.value = false
}

watch(() => props.selectedImage, () => {
  isZoomVisible.value = false
})
</script>
