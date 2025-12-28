<template>
  <StorefrontLayout>
    <div class="mx-auto max-w-2xl space-y-6">
      <div class="space-y-2">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('Support') }}</p>
        <h1 class="text-3xl font-semibold tracking-tight text-slate-900">{{ t('We are here to help') }}</h1>
        <p class="text-sm text-slate-600">
          {{ t('For order issues, customs questions, or delivery updates, reach out and we will respond within 24 hours.') }}
        </p>
      </div>

      <div class="card p-6">
        <div class="space-y-4">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Email') }}</p>
            <p class="text-sm font-semibold text-slate-900">{{ supportEmail }}</p>
          </div>
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('WhatsApp') }}</p>
            <p class="text-sm font-semibold text-slate-900">{{ supportWhatsApp }}</p>
          </div>
          <div v-if="supportPhone">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Phone') }}</p>
            <p class="text-sm font-semibold text-slate-900">{{ supportPhone }}</p>
          </div>
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Hours') }}</p>
            <p class="text-sm text-slate-600">{{ supportHours }}</p>
          </div>
        </div>
      </div>

      <div class="card-muted p-6 text-sm text-slate-600">
        {{ t('Include your order number and the email used at checkout. For customs queries, mention your city and shipment tracking number if available.') }}
      </div>
    </div>
  </StorefrontLayout>
</template>

<script setup>
import { usePage } from '@inertiajs/vue3'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import { useTranslations } from '@/i18n'

const page = usePage()
const { t } = useTranslations()
const supportEmail = page.props.site?.support_email ?? 'support@dispatch.store'
const supportWhatsApp = page.props.site?.support_whatsapp ?? '+225 00 00 00 00'
const supportPhone = page.props.site?.support_phone ?? null
const supportHours = page.props.site?.support_hours ?? 'Mon-Sat, 9:00-18:00 GMT'
</script>
