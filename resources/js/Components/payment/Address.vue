<template>
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

</template>

<script setup>
import {useForm} from "@inertiajs/vue3";
import { useTranslations } from '@/i18n'

const { t } = useTranslations()

const props = defineProps({
    user: { type: Object, default: null },
    defaultAddress: { type: Object, default: null },

})
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
})

</script>

<style scoped>

</style>
