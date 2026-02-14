<template>
  <StorefrontLayout>
    <article class="mx-auto max-w-4xl space-y-8 px-4 py-8">
      <header class="space-y-2">
        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">
          {{ pageTitle }}
        </p>
        <h1 class="text-3xl font-semibold text-slate-900">
          {{ pageTitle }}
        </h1>
        <p v-if="pageDescription" class="text-sm text-slate-600">
          {{ pageDescription }}
        </p>
      </header>

      <div
        v-if="content"
        class="prose prose-slate max-w-none"
        v-html="content"
      ></div>
      <div v-else class="text-center py-12">
        <p class="text-slate-600">{{ t('Content coming soon...') }}</p>
      </div>
    </article>
  </StorefrontLayout>
</template>

<script setup>
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { useTranslations } from '@/i18n';

const { t } = useTranslations();

defineProps({
  content: {
    type: String,
    default: '',
  },
  pageTitle: {
    type: String,
    required: true,
  },
  pageDescription: {
    type: String,
    default: '',
  },
});
</script>
