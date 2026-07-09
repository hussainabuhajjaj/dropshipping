<template>
  <StorefrontLayout>
    <Head :title="t('Download Simbazu App')" />

    <div class="min-h-screen bg-white pb-28">
      <div class="mx-auto max-w-3xl px-4 pt-8 sm:pt-12">
        <div class="text-center">
          <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-2xl bg-slate-900 shadow-lg">
            <svg class="h-10 w-10 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
            </svg>
          </div>
          <h1 class="mt-4 text-3xl font-black tracking-tight text-slate-900 sm:text-4xl">{{ t('Simbazu Mobile App') }}</h1>
          <p class="mt-2 text-sm text-slate-500">{{ t('Shop smarter. Track orders in real-time. Get exclusive mobile deals.') }}</p>
        </div>

        <div class="mt-8 flex flex-col items-center gap-6 sm:flex-row sm:justify-center">
          <a
            :href="downloadUrl"
            class="inline-flex min-h-14 w-full max-w-xs items-center justify-center gap-3 rounded-2xl bg-slate-900 px-8 text-white shadow-lg transition hover:bg-slate-800 active:scale-[0.98]"
          >
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
              <path d="M5 3h4l3 5-3 5H5l3-5zm14 0h-4l-3 5 3 5h4l-3-5zm-7 13l-3 5h4l3-5z"/>
            </svg>
            <div class="text-left">
              <p class="text-[0.55rem] font-semibold uppercase tracking-wider text-white/60">{{ t('Download for Android') }}</p>
              <p class="text-sm font-bold">{{ t('APK v:version', { version: android.version_name }) }}</p>
            </div>
          </a>

          <a
            v-if="ios.appstore_url"
            :href="ios.appstore_url"
            target="_blank"
            class="inline-flex min-h-14 w-full max-w-xs items-center justify-center gap-3 rounded-2xl border-2 border-slate-200 bg-white px-8 text-slate-900 shadow-sm transition hover:border-slate-300 active:scale-[0.98]"
          >
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
              <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.67-.81 1.76-1.4 2.74-1.42.08 1.06-.29 2.09-.94 2.85-.65.77-1.72 1.36-2.76 1.32-.08-1.03.28-2.08.96-2.75z"/>
            </svg>
            <div class="text-left">
              <p class="text-[0.55rem] font-semibold uppercase tracking-wider text-slate-400">{{ t('Available on the') }}</p>
              <p class="text-sm font-bold">{{ t('App Store') }}</p>
            </div>
          </a>
        </div>

        <div v-if="!canDownloadDirectly" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-center text-xs text-amber-800">
          {{ t('The APK is being prepared. Check back soon or join the newsletter for updates.') }}
        </div>

        <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2">
          <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <h3 class="flex items-center gap-2 text-sm font-bold text-slate-900">
              <svg class="h-5 w-5 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              {{ t('App Info') }}
            </h3>
            <dl class="mt-3 space-y-2 text-sm">
              <div class="flex justify-between">
                <dt class="text-slate-500">{{ t('Version') }}</dt>
                <dd class="font-semibold text-slate-900">{{ android.version_name }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-slate-500">{{ t('Size') }}</dt>
                <dd class="font-semibold text-slate-900">{{ android.size_mb }} MB</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-slate-500">{{ t('Requires Android') }}</dt>
                <dd class="font-semibold text-slate-900">{{ t('API :sdk+', { sdk: android.min_sdk }) }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-slate-500">{{ t('Updated') }}</dt>
                <dd class="font-semibold text-slate-900">{{ android.updated_at }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-slate-500">{{ t('Package') }}</dt>
                <dd class="font-semibold text-slate-900 text-xs">{{ android.package_name }}</dd>
              </div>
            </dl>
          </div>

          <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <h3 class="flex items-center gap-2 text-sm font-bold text-slate-900">
              <svg class="h-5 w-5 text-amber-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
              </svg>
              {{ t('What\'s New') }}
            </h3>
            <p class="mt-3 text-sm text-slate-600">{{ android.changelog }}</p>
          </div>
        </div>

        <div class="mt-6 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
          <h3 class="text-sm font-bold text-slate-900">{{ t('Features') }}</h3>
          <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
            <div v-for="feature in features" :key="feature" class="flex items-start gap-2 text-sm text-slate-600">
              <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
              </svg>
              {{ feature }}
            </div>
          </div>
        </div>

        <div class="mt-8 text-center">
          <Link href="/" class="text-xs font-semibold text-slate-400 underline hover:text-slate-600">
            {{ t('Back to Simbazu') }}
          </Link>
        </div>
      </div>
    </div>
  </StorefrontLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { useTranslations } from '@/i18n'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'

const props = defineProps({
  android: { type: Object, required: true },
  ios: { type: Object, required: true },
  features: { type: Array, default: () => [] },
})

const { t } = useTranslations()

const canDownloadDirectly = computed(() => {
  // APK exists if download_url doesn't point to a store
  return props.android.download_url && !props.android.download_url.includes('play.google.com')
})

const downloadUrl = computed(() => {
  return props.android.download_url || route('download.apk')
})
</script>
