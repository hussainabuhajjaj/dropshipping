<template>
  <StorefrontLayout>
    <div class="space-y-8">
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ t('Checkout') }}</h1>

      <div class="grid gap-10 lg:grid-cols-[1.4fr,1fr]">
        <div class="space-y-6">
          <!-- Express Checkout Buttons -->
          <ExpressCheckoutButtons
            :amount="total"
            :currency="currency"
            :stripe-key="stripeKey"
            :paystack-key="paystackKey"
          />

          <form class="space-y-6" @submit.prevent="submit">
          <p v-if="form.errors.payment" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
            {{ form.errors.payment }}
          </p>
          <section class="card p-5">
            <h2 class="text-sm font-semibold text-slate-900">{{ t('Contact') }}</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
              <input v-model="form.email" type="email" required :placeholder="t('Email')" class="input-base" />
              <input v-model="form.phone" type="tel" required :placeholder="t('Phone')" class="input-base" />
            </div>
          </section>

          <section class="card p-5">
            <h2 class="text-sm font-semibold text-slate-900">{{ t('Shipping address') }}</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
              <input v-model="form.first_name" required :placeholder="t('First name')" class="input-base" />
              <input v-model="form.last_name" :placeholder="t('Last name')" class="input-base" />
              <input v-model="form.line1" required :placeholder="t('Address line 1')" class="input-base sm:col-span-2" />
              <input v-model="form.line2" :placeholder="t('Address line 2')" class="input-base sm:col-span-2" />
              <input v-model="form.city" required :placeholder="t('City')" class="input-base" />
              <input v-model="form.state" :placeholder="t('State / Region')" class="input-base" />
              <input v-model="form.postal_code" :placeholder="t('Postal code')" class="input-base" />
              <input v-model="form.country" required :placeholder="t('Country')" class="input-base" />
            </div>
            <textarea
              v-model="form.delivery_notes"
              rows="3"
              :placeholder="t('Delivery notes (optional)')"
              class="input-base mt-4 w-full"
            />
            <p class="mt-3 text-xs text-slate-500">
              {{ t("Duties and VAT for Cote d'Ivoire are shown before payment. By placing the order you acknowledge customs may contact you if additional verification is required.") }}
            </p>
          </section>

          <section class="card p-5">
            <h2 class="text-sm font-semibold text-slate-900">{{ t('Payment method') }}</h2>
            <div class="mt-4 grid gap-3 text-sm text-slate-600">
              <label class="flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-2">
                <input v-model="form.payment_method" type="radio" value="card" />
                <span>{{ t('Card (Visa / Mastercard)') }}</span>
              </label>
              <label class="flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-2">
                <input v-model="form.payment_method" type="radio" value="mobile_money" />
                <span>{{ t('Mobile money') }}</span>
              </label>
              <label class="flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-2">
                <input v-model="form.payment_method" type="radio" value="bank_transfer" />
                <span>{{ t('Bank transfer') }}</span>
              </label>
            </div>
          </section>

          <section class="card p-5">
            <label class="flex items-start gap-3 text-sm text-slate-600">
              <input v-model="form.accept_terms" type="checkbox" />
              <span>
                {{ t('I agree to the') }}
                <a class="font-semibold text-slate-900 hover:text-slate-700" href="/legal/terms-of-service">{{ t('terms') }}</a>
                {{ t('and') }}
                <a class="font-semibold text-slate-900 hover:text-slate-700" href="/legal/refund-policy">{{ t('refund policy') }}</a>.
              </span>
            </label>
          </section>

          <button type="submit" class="btn-primary w-full">
            {{ t('Place order') }}
          </button>
        </form>
        </div>

        <aside class="card-muted space-y-4 p-5">
          <div class="flex items-center justify-between text-sm">
            <span>{{ t('Subtotal') }}</span>
            <span class="font-semibold text-slate-900">{{ currency }} {{ subtotal.toFixed(2) }}</span>
          </div>
          <div v-if="discount > 0" class="flex items-center justify-between text-sm text-green-700">
            <span>{{ t('Discount') }} <span v-if="coupon?.code">({{ coupon.code }})</span></span>
            <span>- {{ currency }} {{ discount.toFixed(2) }}</span>
          </div>
          <div class="flex items-center justify-between text-sm">
            <span>
              {{ t('Shipping') }} <span class="text-xs text-slate-400">({{ shipping_method }})</span>
            </span>
            <span class="text-slate-600">{{ currency }} {{ shipping.toFixed(2) }}</span>
          </div>
          <p class="text-[0.65rem] text-slate-500">
            {{ t('Your order may arrive in multiple packages and tracking numbers may update separately.') }}
          </p>
          <p class="text-[0.65rem] text-slate-500">
            {{ t('Shipping costs are estimated until you provide an address; the total will refresh before payment once final rates are fetched.') }}
          </p>
          <div class="flex items-center justify-between text-sm">
            <span>
              {{ tax_label }} <span v-if="tax_included" class="text-xs text-slate-400">({{ t('included') }})</span>
            </span>
            <span class="text-slate-600">{{ currency }} {{ tax_total.toFixed(2) }}</span>
          </div>
          <div class="flex items-center justify-between text-base font-semibold text-slate-900">
            <span>{{ t('Total') }}</span>
            <span>{{ currency }} {{ total.toFixed(2) }}</span>
          </div>
          <p class="text-xs text-slate-500">
            {{ t('Delivery estimates and customs details are emailed after payment. Tracking updates within 24 to 48 hours post fulfillment.') }}
          </p>
        </aside>
      </div>
    </div>
  </StorefrontLayout>
</template>

<script setup>
import { toRefs } from 'vue'
import { useForm } from '@inertiajs/vue3'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import ExpressCheckoutButtons from '@/Components/ExpressCheckoutButtons.vue'
import { useTranslations } from '@/i18n'

const props = defineProps({
  subtotal: { type: Number, default: 0 },
  total: { type: Number, default: 0 },
  currency: { type: String, default: 'USD' },
  shipping_method: { type: String, default: 'standard' },
  discount: { type: Number, default: 0 },
  coupon: { type: Object, default: null },
  tax_total: { type: Number, default: 0 },
  tax_label: { type: String, default: 'Tax' },
  tax_included: { type: Boolean, default: false },
  user: { type: Object, default: null },
  defaultAddress: { type: Object, default: null },
  shipping: { type: Number, default: 0 },
  stripeKey: { type: String, default: '' },
  paystackKey: { type: String, default: '' },
})

const { t } = useTranslations()
const { subtotal, total, currency, shipping_method, discount, coupon, tax_total, tax_label, tax_included, shipping } = toRefs(props)

const form = useForm({
  email: props.user?.email || '',
  phone: props.user?.phone || '',
  first_name: props.defaultAddress?.name || props.user?.name || '',
  last_name: '',
  line1: props.defaultAddress?.line1 || '',
  line2: props.defaultAddress?.line2 || '',
  city: props.defaultAddress?.city || '',
  state: props.defaultAddress?.state || '',
  postal_code: props.defaultAddress?.postal_code || '',
  country: props.defaultAddress?.country || 'CI',
  delivery_notes: '',
  payment_method: 'card',
  accept_terms: false,
})

const submit = () => {
  form.post('/checkout')
}
</script>
