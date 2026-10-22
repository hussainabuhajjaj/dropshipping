<template>
  <StorefrontLayout>
    <article class="mx-auto max-w-3xl space-y-8">
      <header class="space-y-2">
        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">{{ t('Policy') }}</p>
        <h1 class="text-3xl font-semibold text-slate-900">{{ t('Privacy Policy') }}</h1>
        <p class="text-sm text-slate-600">
          {{ t('How we collect, use, and protect your data when you shop with us.') }}
        </p>
      </header>

      <div
        v-if="policyHtml"
        class="space-y-4 text-sm text-slate-600 leading-7 whitespace-pre-line"
        v-html="policyHtml"
      ></div>
      <template v-else>
        <section class="space-y-3">
          <h2 class="text-lg font-semibold text-slate-900">{{ t('Data We Collect') }}</h2>
          <ul class="space-y-2 text-sm text-slate-600 list-disc list-inside">
            <li>{{ t('Contact info: name, email, phone.') }}</li>
            <li>{{ t('Order details: items, totals, shipping address.') }}</li>
            <li>{{ t('Technical: device/browser info for security and performance.') }}</li>
          </ul>
        </section>

        <section class="space-y-3">
          <h2 class="text-lg font-semibold text-slate-900">{{ t('How We Use Data') }}</h2>
          <ul class="space-y-2 text-sm text-slate-600 list-disc list-inside">
            <li>{{ t('Process and fulfill your orders.') }}</li>
            <li>{{ t('Provide shipping updates and support.') }}</li>
            <li>{{ t('Improve site performance and prevent fraud.') }}</li>
          </ul>
        </section>

        <section class="space-y-3">
          <h2 class="text-lg font-semibold text-slate-900">{{ t('Sharing') }}</h2>
          <p class="text-sm text-slate-600">
            {{ t('We share data with payment processors, suppliers, and carriers solely to fulfill your order. We do not sell personal data.') }}
          </p>
        </section>

        <section class="space-y-3">
          <h2 class="text-lg font-semibold text-slate-900">{{ t('Security') }}</h2>
          <p class="text-sm text-slate-600">
            {{ t('We use industry-standard security controls and limit access to authorized staff. Payment details are handled by certified processors.') }}
          </p>
        </section>

        <section class="space-y-3">
          <h2 class="text-lg font-semibold text-slate-900">{{ t('Your Choices') }}</h2>
          <p class="text-sm text-slate-600">
            {{ t('You can request data access or deletion where applicable. Contact :email with your request.', { email: supportEmail }) }}
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
const policyHtml = (site?.privacy_policy ?? '').trim()
const supportEmail = site?.support_email ?? 'support@dispatch.store'
</script>
