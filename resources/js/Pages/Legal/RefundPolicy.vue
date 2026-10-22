<template>
  <StorefrontLayout>
    <article class="mx-auto max-w-3xl space-y-8">
      <header class="space-y-2">
        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">{{ t('Policy') }}</p>
        <h1 class="text-3xl font-semibold text-slate-900">{{ t('Refund Policy') }}</h1>
        <p class="text-sm text-slate-600">
          {{ t('How we handle cancellations, returns, and refunds for orders shipped to Cote d\'Ivoire.') }}
        </p>
      </header>

      <div
        v-if="policyHtml"
        class="space-y-4 text-sm text-slate-600 leading-7 whitespace-pre-line"
        v-html="policyHtml"
      ></div>
      <template v-else>
        <section class="space-y-3">
          <h2 class="text-lg font-semibold text-slate-900">{{ t('Eligibility') }}</h2>
          <ul class="space-y-2 text-sm text-slate-600 list-disc list-inside">
            <li>{{ t('Damaged on arrival with photo evidence.') }}</li>
            <li>{{ t('Incorrect item or missing items.') }}</li>
            <li>{{ t('Delivery not completed within the stated window after investigation.') }}</li>
          </ul>
        </section>

        <section class="space-y-3">
          <h2 class="text-lg font-semibold text-slate-900">{{ t('Process') }}</h2>
          <ol class="space-y-2 text-sm text-slate-600 list-decimal list-inside">
            <li>{{ t('Submit a request with order number, photos (if damaged), and tracking info.') }}</li>
            <li>{{ t('We investigate with the supplier/carrier and confirm eligibility.') }}</li>
            <li>{{ t('Refunds are issued to the original payment method; processing times vary by provider.') }}</li>
          </ol>
        </section>

        <section class="space-y-3">
          <h2 class="text-lg font-semibold text-slate-900">{{ t('Exclusions') }}</h2>
          <p class="text-sm text-slate-600">
            {{ t('Requests outside the return window, items used/worn, or cases where customs duties were unpaid and shipment was returned may be excluded unless required by law.') }}
          </p>
        </section>

        <section class="space-y-3">
          <h2 class="text-lg font-semibold text-slate-900">{{ t('Partial Refunds') }}</h2>
          <p class="text-sm text-slate-600">
            {{ t('For partial item issues, we may refund the affected items only. Shipping may be refunded if the entire order fails delivery.') }}
          </p>
        </section>

        <section class="space-y-3">
          <h2 class="text-lg font-semibold text-slate-900">{{ t('Contact') }}</h2>
          <p class="text-sm text-slate-600">
            {{ t('Email :email with your order number and details to start a refund request.', { email: supportEmail }) }}
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
const policyHtml = (site?.refund_policy ?? '').trim()
const supportEmail = site?.support_email ?? 'support@dispatch.store'
</script>
