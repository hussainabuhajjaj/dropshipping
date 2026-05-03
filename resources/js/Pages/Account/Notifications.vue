<template>
  <StorefrontLayout>
    <div class="space-y-5 pb-10 sm:space-y-8">
      <section class="rounded-[1.8rem] bg-[#111111] px-5 py-5 text-white shadow-[0_20px_48px_rgba(15,23,42,0.16)]">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="text-[0.68rem] font-bold uppercase tracking-[0.24em] text-[#facc15]">Account</p>
            <h1 class="mt-2 text-[1.95rem] font-black tracking-[-0.04em] sm:text-[2.2rem]">Notifications</h1>
            <p class="mt-2 max-w-xl text-sm leading-6 text-white/72">Shipping alerts, payment status, and account messages compressed into one fast inbox.</p>
          </div>
          <div class="flex items-center gap-2">
            <Link href="/account" class="inline-flex min-h-11 items-center justify-center rounded-full border border-white/15 bg-white/8 px-4 text-sm font-semibold text-white transition hover:bg-white/12">Back to profile</Link>
          </div>
        </div>
      </section>

      <div class="flex gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        <div class="shrink-0 rounded-full bg-[#fff4e8] px-3 py-2 text-[0.7rem] font-bold uppercase tracking-[0.16em] text-[#c55b24]">{{ unreadCount }} unread</div>
        <div class="shrink-0 rounded-full bg-white px-3 py-2 text-[0.7rem] font-bold uppercase tracking-[0.16em] text-slate-500 ring-1 ring-[#eadfce]">Live order updates</div>
        <div class="shrink-0 rounded-full bg-white px-3 py-2 text-[0.7rem] font-bold uppercase tracking-[0.16em] text-slate-500 ring-1 ring-[#eadfce]">Low-friction inbox</div>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="text-sm text-slate-500">
          {{ unreadCount ? 'Keep the newest actions visible so shoppers do not lose checkout or delivery momentum.' : 'No urgent actions right now. New updates will appear here automatically.' }}
        </div>
        <div class="flex items-center gap-2">
          <button
            type="button"
            class="inline-flex min-h-11 items-center justify-center rounded-full bg-[#111111] px-4 text-xs font-bold uppercase tracking-[0.14em] text-white transition hover:bg-[#262626]"
            :class="{ 'cursor-not-allowed opacity-60': !unreadCount }"
            :disabled="!unreadCount"
            @click="markAllRead"
          >
            Mark all read
          </button>
        </div>
      </div>

      <div v-if="notifications.length" class="space-y-3">
        <article
          v-for="notification in notifications"
          :key="notification.id"
          class="flex flex-wrap items-start justify-between gap-4 rounded-[1.6rem] border p-4 text-sm shadow-[0_14px_34px_rgba(15,23,42,0.05)] sm:p-5"
          :class="notification.read_at ? 'border-[#eadfce] bg-white' : 'border-[#f7c16d] bg-[#fff8ee]'"
        >
          <div class="min-w-0 flex-1 space-y-2">
            <div class="flex flex-wrap items-center gap-2">
              <span
                class="rounded-full px-2.5 py-1 text-[0.64rem] font-bold uppercase tracking-[0.16em]"
                :class="notification.read_at ? 'bg-slate-100 text-slate-500' : 'bg-[#111111] text-white'"
              >
                {{ notification.read_at ? 'Read' : 'New' }}
              </span>
              <span class="text-[0.68rem] font-semibold uppercase tracking-[0.16em] text-slate-400">{{ formatDate(notification.created_at) }}</span>
            </div>
            <p class="text-base font-bold tracking-[-0.02em] text-slate-900">{{ notification.title }}</p>
            <p class="text-sm leading-6 text-slate-600">{{ notification.body }}</p>
            <a
              v-if="notification.action_url"
              :href="notification.action_url"
              class="inline-flex min-h-10 items-center gap-1 rounded-full border border-[#eadfce] bg-white px-3 text-xs font-semibold text-slate-900 transition hover:border-slate-300"
            >
              {{ notification.action_label || 'View details' }}
              <svg viewBox="0 0 20 20" class="h-3.5 w-3.5" fill="currentColor">
                <path d="M7 5l5 5-5 5" />
              </svg>
            </a>
          </div>

          <div class="flex items-center gap-2">
            <button
              v-if="!notification.read_at"
              type="button"
              class="inline-flex min-h-10 items-center justify-center rounded-full border border-[#eadfce] bg-white px-3 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:text-slate-900"
              @click="markRead(notification.id)"
            >
              Mark read
            </button>
          </div>
        </article>
      </div>
      <div v-else class="rounded-[1.6rem] border border-[#eadfce] bg-[#fffaf4] p-6 text-sm text-slate-500 shadow-[0_14px_34px_rgba(15,23,42,0.05)]">
        No notifications yet. We will alert you when something needs attention.
      </div>
    </div>
  </StorefrontLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'

const props = defineProps({
  notifications: { type: Array, default: () => [] },
  unreadCount: { type: Number, default: 0 },
})

const markRead = (id) => {
  router.post(`/account/notifications/${id}/read`, {}, { preserveScroll: true })
}

const markAllRead = () => {
  router.post('/account/notifications/read-all', {}, { preserveScroll: true })
}

const formatDate = (value) => {
  if (! value) {
    return ''
  }
  return new Date(value).toLocaleString()
}
</script>
