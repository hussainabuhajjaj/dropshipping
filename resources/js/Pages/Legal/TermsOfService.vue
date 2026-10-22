<template>
  <StorefrontLayout>
    <article class="mx-auto max-w-3xl space-y-8">
      <header class="space-y-2">
        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">{{ t('Terms') }}</p>
        <h1 class="text-3xl font-semibold text-slate-900">{{ t('Terms of Service') }}</h1>
        <p class="text-sm text-slate-600">
          {{ t('The conditions for using our site and services. Please read carefully before ordering.') }}
        </p>
      </header>

      <div
        v-if="policyHtml"
        class="space-y-4 text-sm text-slate-600 leading-7 whitespace-pre-line"
        v-html="policyHtml"
      ></div>
      <template v-else>
        <section class="space-y-3">
          <h2 class="text-lg font-semibold text-slate-900">{{ t('Use of Service') }}</h2>
          <p class="text-sm text-slate-600">
            {{ t('By placing an order, you confirm the information provided is accurate and you have authority to use the payment method.') }}
          </p>
        </section>

        <section class="space-y-3">
          <h2 class="text-lg font-semibold text-slate-900">{{ t('Products & Pricing') }}</h2>
          <p class="text-sm text-slate-600">
            {{ t('Prices and availability are subject to change. We reserve the right to cancel orders in the event of pricing errors or supplier issues; refunds will be issued in such cases.') }}
          </p>
        </section>

        <section class="space-y-3">
          <h2 class="text-lg font-semibold text-slate-900">{{ t('Shipping & Customs') }}</h2>
          <p class="text-sm text-slate-600">
            {{ t('Delivery estimates are provided at checkout. Customs duties and VAT for Cote d\'Ivoire are disclosed when available; local authorities may request additional verification or payment.') }}
          </p>
        </section>

        <section class="space-y-3">
          <h2 class="text-lg font-semibold text-slate-900">{{ t('Returns & Refunds') }}</h2>
          <p class="text-sm text-slate-600">
            {{ t('Returns and refunds follow our Refund Policy. Approved refunds are issued to the original payment method.') }}
          </p>
        </section>

        <section class="space-y-3">
          <h2 class="text-lg font-semibold text-slate-900">{{ t('Limitation of Liability') }}</h2>
          <p class="text-sm text-slate-600">
            {{ t('To the extent permitted by law, our liability for any claim is limited to the amount paid for the order in question.') }}
          </p>
        </section>

        <section class="space-y-3">
          <h2 class="text-lg font-semibold text-slate-900">{{ t('Contact') }}</h2>
          <p class="text-sm text-slate-600">
            {{ t('For questions about these terms, email :email.', { email: supportEmail }) }}
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
const policyHtml = (site?.terms_of_service ?? '').trim()
const supportEmail = site?.support_email ?? 'support@dispatch.store'
</script>
