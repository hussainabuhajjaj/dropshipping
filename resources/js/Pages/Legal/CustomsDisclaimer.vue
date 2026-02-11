<template>
  <StorefrontLayout>
    <article class="mx-auto max-w-3xl space-y-8">
      <header class="space-y-2">
        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">{{ t('Customs') }}</p>
        <h1 class="text-3xl font-semibold text-slate-900">{{ t('Customs Disclaimer') }}</h1>
        <p class="text-sm text-slate-600">
          {{ t('What to expect during customs clearance for shipments to Cote d\'Ivoire.') }}
        </p>
      </header>

      <div
        v-if="policyHtml"
        class="space-y-4 text-sm text-slate-600 leading-7 whitespace-pre-line"
        v-html="policyHtml"
      ></div>
      <template v-else>
        <section class="space-y-3">
          <h2 class="text-lg font-semibold text-slate-900">{{ t('Duties & VAT') }}</h2>
          <p class="text-sm text-slate-600">
            {{ t('Duties and VAT are estimated and shown at checkout when available. Final charges are determined by local customs and may vary. You are responsible for any official customs fees.') }}
          </p>
        </section>

        <section class="space-y-3">
          <h2 class="text-lg font-semibold text-slate-900">{{ t('Documentation') }}</h2>
          <p class="text-sm text-slate-600">
            {{ t('Customs may request identification or additional documents. Please respond promptly to avoid delays.') }}
          </p>
        </section>

        <section class="space-y-3">
          <h2 class="text-lg font-semibold text-slate-900">{{ t('Delays') }}</h2>
          <p class="text-sm text-slate-600">
            {{ t('Clearance times can vary. If a delay occurs, we will assist with updates and work with the carrier to resolve issues.') }}
          </p>
        </section>

        <section class="space-y-3">
          <h2 class="text-lg font-semibold text-slate-900">{{ t('Contact') }}</h2>
          <p class="text-sm text-slate-600">
            {{ t('For customs questions, reach out at :email with your order number and tracking details.', { email: supportEmail }) }}
          </p>
        </section>
      </template>
    </article>
  </StorefrontLayout>
</template>

<script setup>
import { computed } from 'vue'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import { usePage } from '@inertiajs/vue3'
import { useTranslations } from '@/i18n'

const props = defineProps({
  policyHtml: { type: String, default: '' },
  supportEmail: { type: String, default: null },
})

const site = usePage().props.site
const { t } = useTranslations()
const policyHtml = computed(() => (props.policyHtml || site?.customs_disclaimer || '').trim())
const supportEmail = computed(() => props.supportEmail || site?.support_email || 'support@dispatch.store')
</script>
