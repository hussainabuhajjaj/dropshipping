<template>
  <nav class="sticky z-20 rounded-full bg-white/88 p-1.5 shadow-[0_14px_34px_rgba(15,23,42,0.08)] ring-1 ring-[#ebe2d6] backdrop-blur" :style="{ top: offset }">
    <div class="flex gap-1 overflow-x-auto [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
      <button
        v-for="item in items"
        :key="item.id"
        type="button"
        class="inline-flex min-h-11 shrink-0 items-center rounded-full px-3 py-2 text-[0.68rem] font-bold uppercase tracking-[0.16em] transition"
        :class="activeId === item.id ? 'bg-[#111111] text-white' : 'text-slate-500 hover:text-slate-900'"
        @click="scrollTo(item.id)"
      >
        {{ item.label }}
      </button>
    </div>
  </nav>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps({
  items: { type: Array, default: () => [] },
  offset: { type: String, default: '6rem' },
})

const activeId = ref(props.items[0]?.id ?? null)
const observer = ref(null)

const scrollTo = (id) => {
  activeId.value = id
  const element = document.getElementById(id)
  if (!element) return
  element.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const connectObserver = () => {
  if (typeof window === 'undefined') return
  observer.value?.disconnect()

  const sections = props.items
    .map((item) => document.getElementById(item.id))
    .filter(Boolean)

  if (!sections.length) return

  observer.value = new IntersectionObserver(
    (entries) => {
      const visible = entries
        .filter((entry) => entry.isIntersecting)
        .sort((a, b) => b.intersectionRatio - a.intersectionRatio)

      if (visible[0]?.target?.id) {
        activeId.value = visible[0].target.id
      }
    },
    {
      rootMargin: '-18% 0px -62% 0px',
      threshold: [0.15, 0.35, 0.6],
    }
  )

  sections.forEach((section) => observer.value.observe(section))
}

watch(() => props.items, connectObserver, { deep: true })

onMounted(connectObserver)
onBeforeUnmount(() => observer.value?.disconnect())
</script>
