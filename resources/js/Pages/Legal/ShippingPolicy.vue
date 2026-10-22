<template>
  <StorefrontLayout>
    <article class="mx-auto max-w-3xl space-y-8">
      <header class="space-y-2">
        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">{{ t('Policy') }}</p>
        <h1 class="text-3xl font-semibold text-slate-900">{{ t('Shipping Policy') }}</h1>
        <p class="text-sm text-slate-600">
          {{ t('Clear timelines and responsibilities for orders shipping to Cote d\'Ivoire.') }}
        </p>
      </header>

      <div
        v-if="policyHtml"
        class="space-y-4 text-sm text-slate-600 leading-7 whitespace-pre-line"
        v-html="policyHtml"
      ></div>
      <template v-else>
        <section class="space-y-3">
          <h2 class="text-lg font-semibold text-slate-900">{{ t('Processing & Dispatch') }}</h2>
          <p class="text-sm text-slate-600">
            {{ t('Orders are processed within 24 hours on business days. Dispatch from suppliers typically occurs within 2 to 5 business days unless otherwise stated on the product page.') }}
          </p>
        </section>

        <section class="space-y-3">
          <h2 class="text-lg font-semibold text-slate-900">{{ t('Delivery Timeframes') }}</h2>
          <ul class="space-y-2 text-sm text-slate-600 list-disc list-inside">
            <li>{{ t('Standard tracked delivery to Cote d\'Ivoire: :window (estimate).', { window: deliveryWindow }) }}</li>
            <li>{{ t('Tracking is provided once the supplier ships; updates continue through delivery.') }}</li>
            <li>{{ t('Peak periods or carrier constraints may extend delivery; we will notify you of delays.') }}</li>
          </ul>
        </section>

        <section class="space-y-3">
          <h2 class="text-lg font-semibold text-slate-900">{{ t('Customs & Duties') }}</h2>
          <p class="text-sm text-slate-600">
            {{ t('Duties and VAT are shown at checkout when available. Local customs may request ID or additional payment based on regulation; we will support you if contacted.') }}
          </p>
        </section>

        <section class="space-y-3">
          <h2 class="text-lg font-semibold text-slate-900">{{ t('Lost or Damaged Parcels') }}</h2>
          <p class="text-sm text-slate-600">
            {{ t('If an order arrives damaged or does not arrive within the expected timeframe, contact support with your order number and tracking details. We will investigate with the carrier and supplier and issue replacement or refund as applicable.') }}
          </p>
        </section>

        <section class="space-y-3">
          <h2 class="text-lg font-semibold text-slate-900">{{ t('Contact') }}</h2>
          <p class="text-sm text-slate-600">
            {{ t('For shipping questions, email :email or use the "Track Order" page with your order number and email.', { email: supportEmail }) }}
          </p>
        </section>
      </template>
    </article>
  </StorefrontLayout>
</template>

<script setup>
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import { usePage } from '@inertiajs/vue3'
import { useTranslations } from '@/i18n'

const site = usePage().props.site
const { t } = useTranslations()
const policyHtml = (site?.shipping_policy ?? '').trim()
const deliveryWindow = site?.delivery_window ?? '7 to 30 business days'
const supportEmail = site?.support_email ?? 'support@dispatch.store'
</script>
