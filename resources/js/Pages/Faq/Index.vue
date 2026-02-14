<template>
  <StorefrontLayout>
    <div class="mx-auto max-w-4xl space-y-8">
      <header class="space-y-2">
        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">{{ t('FAQ') }}</p>
        <h1 class="text-3xl font-semibold text-slate-900">{{ t('Answers to common questions') }}</h1>
        <p class="text-sm text-slate-600">
          {{ t('Delivery timelines, customs, and payment information for cross-border orders.') }}
        </p>
      </header>

      <section class="grid gap-4 sm:grid-cols-2">
        <div
          v-for="item in faqs"
          :key="item.question"
          class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
        >
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-orange-600">{{ t(item.category) }}</p>
          <h2 class="mt-2 text-sm font-semibold text-slate-900">{{ t(item.question) }}</h2>
          <p class="mt-2 text-sm leading-relaxed text-slate-600">
            {{ t(item.answer) }}
          </p>
        </div>
      </section>

      <section class="rounded-xl border border-slate-200 bg-slate-50 p-6">
        <h2 class="text-base font-semibold text-slate-900">{{ t('Need help with a specific order?') }}</h2>
        <p class="mt-2 text-sm text-slate-600">
          {{ t('Contact support with your order number and we will check status, shipping updates, or refund eligibility for you.') }}
        </p>
        <div class="mt-4 flex flex-wrap gap-3">
          <a
            :href="route('contact')"
            class="inline-flex items-center rounded-md bg-orange-600 px-4 py-2 text-xs font-semibold text-white hover:bg-orange-700"
          >
            {{ t('Contact support') }}
          </a>
          <a
            :href="route('orders.track')"
            class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100"
          >
            {{ t('Track order') }}
          </a>
        </div>
      </section>
    </div>
  </StorefrontLayout>
</template>

<script setup>
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import { useTranslations } from '@/i18n'

const { t } = useTranslations()

const faqs = [
  {
    category: 'Delivery',
    question: 'How long does delivery take?',
    answer: 'Most orders arrive in 7 to 18 business days after dispatch. Tracking is usually available within 24 to 48 hours.',
  },
  {
    category: 'Delivery',
    question: 'Do you ship nationwide?',
    answer: 'Yes. We deliver across Cote d\'Ivoire through local delivery partners, depending on address coverage and courier availability.',
  },
  {
    category: 'Shipping',
    question: 'How do shipping costs work?',
    answer: 'Shipping fees are calculated at checkout based on your destination, product type, and supplier logistics options.',
  },
  {
    category: 'Customs',
    question: 'Will I pay customs duties?',
    answer: 'Duties and VAT are shown before checkout when available. In some cases, customs or carriers may collect additional amounts at delivery.',
  },
  {
    category: 'Orders',
    question: 'Can I cancel an order after payment?',
    answer: 'You can request cancellation before processing starts. Once dispatched, cancellation is no longer guaranteed and refund rules apply.',
  },
  {
    category: 'Orders',
    question: 'Can I change my address after ordering?',
    answer: 'Address changes are possible shortly after purchase. Contact support immediately with your order number to check if update is still possible.',
  },
  {
    category: 'Tracking',
    question: 'Why has my tracking not updated yet?',
    answer: 'Tracking can pause at transit hubs or customs checkpoints. If no update appears for several days, contact support and we will investigate.',
  },
  {
    category: 'Products',
    question: 'Are product photos and colors exact?',
    answer: 'We strive for accuracy, but slight differences in color, packaging, or minor details may occur between suppliers and production batches.',
  },
  {
    category: 'Quality',
    question: 'What if my product is damaged or incorrect?',
    answer: 'Report the issue within 7 days of delivery with photos and your order number. We will review and offer replacement, partial refund, or full refund when eligible.',
  },
  {
    category: 'Returns',
    question: 'Do you accept returns?',
    answer: 'Returns are accepted for eligible cases under our refund policy. Approval depends on condition, proof, and the reason for return.',
  },
  {
    category: 'Refunds',
    question: 'How long do refunds take?',
    answer: 'After approval, refunds are processed to the original payment method. Provider timelines vary and may take several business days.',
  },
  {
    category: 'Payments',
    question: 'Which payment methods are accepted?',
    answer: 'Available payment methods are shown at checkout and can include card and supported local providers, depending on your account and region.',
  },
  {
    category: 'Security',
    question: 'Is my payment information secure?',
    answer: 'Payments are handled through secure providers. We do not store full card numbers on our platform.',
  },
  {
    category: 'Account',
    question: 'Do I need an account to place an order?',
    answer: 'Guest checkout may be available for some orders, but creating an account helps with faster checkout and easier tracking.',
  },
  {
    category: 'Support',
    question: 'How can I reach support quickly?',
    answer: 'Use the Contact page or WhatsApp support and include your order number so we can resolve your request faster.',
  },
]
</script>
