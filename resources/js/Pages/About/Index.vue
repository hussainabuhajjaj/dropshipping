<template>
  <StorefrontLayout>
    <div class="mx-auto max-w-4xl px-4 py-8">
      <!-- Header -->
      <header class="mb-12 text-center">
        <h1 class="text-4xl font-bold text-slate-900 mb-4">
          {{ pageTitle }}
        </h1>
        <p class="text-lg text-slate-600">
          {{ pageDescription }}
        </p>
      </header>

      <!-- Content -->
      <div
        v-if="content"
        class="prose prose-slate max-w-none prose-headings:text-slate-900 prose-h1:text-3xl prose-h2:text-2xl prose-h2:mt-8 prose-h2:mb-4 prose-p:text-slate-600 prose-li:text-slate-600 prose-a:text-orange-600 hover:prose-a:text-orange-700"
        v-html="content"
      ></div>

      <!-- Fallback content if no database content -->
      <template v-else>
        <div class="space-y-8">
          <section class="space-y-4">
            <h2 class="text-2xl font-semibold text-slate-900">{{ t('Built for cross-border clarity') }}</h2>
            <p class="text-slate-600">
              {{ t("Azura helps customers in Cote d'Ivoire access global products with predictable delivery and transparent customs. We coordinate suppliers, track orders, and keep you informed.") }}
            </p>
          </section>

          <section class="grid gap-6 sm:grid-cols-2">
            <div class="rounded-lg border border-slate-200 p-6">
              <h3 class="text-lg font-semibold text-slate-900 mb-2">{{ t('Our promise') }}</h3>
              <p class="text-sm text-slate-600">
                {{ t('We disclose duties before payment, monitor fulfillment, and provide tracking from dispatch to delivery.') }}
              </p>
            </div>
            <div class="rounded-lg border border-slate-200 p-6">
              <h3 class="text-lg font-semibold text-slate-900 mb-2">{{ t('Local support') }}</h3>
              <p class="text-sm text-slate-600">
                {{ t('Support is available via WhatsApp or email with local context and fast resolution.') }}
              </p>
            </div>
          </section>

          <section class="rounded-lg bg-slate-50 p-6 text-sm text-slate-600">
            {{ t('We partner with vetted suppliers and prioritize products with reliable logistics. Each order is reviewed to ensure a smooth delivery experience.') }}
          </section>
        </div>
      </template>

      <!-- Contact CTA -->
      <div class="mt-16 rounded-lg bg-slate-50 p-8 text-center">
        <h3 class="text-xl font-semibold text-slate-900 mb-2">
          {{ t('Have Questions?') }}
        </h3>
        <p class="text-slate-600 mb-4">
          {{ t('Our support team is here to help') }}
        </p>
        <a
          :href="route('contact')"
          class="inline-flex items-center justify-center rounded-md bg-orange-600 px-6 py-3 text-sm font-semibold text-white hover:bg-orange-700 transition-colors"
        >
          {{ t('Contact Support') }}
        </a>
      </div>
    </div>
  </StorefrontLayout>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';

const { t } = useI18n();

defineProps({
  content: {
    type: String,
    default: '',
  },
  pageTitle: {
    type: String,
    default: 'About Us',
  },
  pageDescription: {
    type: String,
    default: 'Learn more about our story, mission, and values.',
  },
});
</script>
