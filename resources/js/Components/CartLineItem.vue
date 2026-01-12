<template>
    <div class="card flex flex-col gap-4 p-4 sm:flex-row sm:items-center">
        <div class="flex flex-1 items-center gap-4">
            <div class="h-20 w-20 overflow-hidden rounded-xl bg-slate-50">
                <img
                    v-if="line.media?.[0]"
                    :src="line.media[0]"
                    :alt="line.name"
                    class="h-full w-full object-cover"
                />
            </div>
            <div class="space-y-1">
                <p class="text-sm font-semibold text-slate-900">{{ line.name }}</p>
                <p class="text-xs text-slate-500">{{
                        t('Variant: :variant', {variant: line.variant ?? t('Default')})
                    }}</p>
                <p class="text-xs text-slate-500">
                    {{ t('Unit price: :amount', {amount: displayUnitPrice}) }}
                </p>
                <button
                    type="button"
                    class="btn-ghost px-0 text-xs"
                    @click="$emit('remove', line.id)"
                >
                    {{ t('Remove') }}
                </button>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-2 rounded-full border border-slate-200 px-2 py-1">
                <button
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-sm text-slate-600"
                    @click="$emit('update', line.id, Math.max(1, line.quantity - 1))"
                >
                    -
                </button>
                <input
                    :value="line.quantity"
                    type="number"
                    min="1"
                    class="h-8 w-14 rounded-lg border border-slate-300 bg-white text-center text-xs text-slate-700 focus:border-slate-500 focus:outline-none justify-center"
                    @change="$emit('update', line.id, Math.max(1, Number($event.target.value || 1)))"
                />
                <button
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-sm text-slate-600"
                    @click="$emit('update', line.id, line.quantity + 1)"
                >
                    +
                </button>
            </div>
            <div class="text-sm font-semibold text-slate-900">
                {{ displayTotalPrice }}
            </div>
        </div>
    </div>
</template>

<script setup>
import {convertCurrency, formatCurrency} from '@/utils/currency.js'

const displayUnitPrice = formatCurrency(convertCurrency(Number(__props.line.price ?? 0), 'USD', __props.currency), __props.currency)
// const displayTotalPrice = formatCurrency(convertCurrency(Number((__props.line.price ?? 0) * (__props.line.quantity ?? 1)), 'USD', __props.currency), __props.currency)
import {useTranslations} from '@/i18n'
import {computed} from "vue";

defineProps({
    line: {type: Object, required: true},
    currency: {type: String, default: 'USD'},
})

const displayTotalPrice = computed(() => {
    return formatCurrency(convertCurrency(Number((__props.line.price ?? 0) * (__props.line.quantity ?? 1)), 'USD', __props.currency), __props.currency);
})

defineEmits(['remove', 'update'])

const {t} = useTranslations()
</script>
