<template>
    <div class="card flex flex-col gap-4 p-4">
        <div class="flex items-start gap-3 sm:gap-4">
            <div class="h-24 w-24 shrink-0 overflow-hidden rounded-2xl bg-slate-50 sm:h-20 sm:w-20">
                <img
                    v-if="line.media?.[0]"
                    :src="line.media[0]"
                    :alt="line.name"
                    class="h-full w-full object-cover"
                />
            </div>
            <div class="min-w-0 flex-1 space-y-1.5">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-sm font-semibold text-slate-900 line-clamp-2">{{ line.name }}</p>
                    <button
                        type="button"
                        class="shrink-0 rounded-full px-2 py-1 text-xs font-medium text-red-600 transition hover:bg-red-50" @click="$emit('remove', line.id)"
                    >
                        {{ t('Remove') }}
                    </button>
                </div>
                <p class="text-xs text-slate-500">{{
                        t('Variant: :variant', {variant: line.variant ?? t('Default')})
                    }}</p>
                <div class="flex flex-wrap items-center gap-2">
                    <span v-if="stockState.label" :class="stockState.class" class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-semibold">
                        {{ stockState.label }}
                    </span>
                    <span v-if="lineSavings > 0" class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-semibold text-emerald-700">
                        {{ t('Save :amount', { amount: displayPrice(lineSavings) }) }}
                    </span>
                </div>
                <p v-if="linePromotion" class="text-[10px] font-semibold text-amber-700">
                    {{ linePromotion.name }}
                    <span v-if="linePromotion.value_type === 'percentage'">-{{ linePromotion.value }}%</span>
                    <span v-else-if="linePromotion.value_type === 'fixed'">-{{ displayPrice(linePromotion.value) }}</span>
                    <span v-if="promoCountdown" class="ml-1">· {{ t('Ends in') }} {{ promoCountdown }}</span>
                </p>
                <p class="text-xs text-slate-500">
                    {{ t('Unit price: :amount', {amount: displayUnitPrice}) }}
                </p>
            </div>
        </div>

        <div class="flex flex-col gap-3 border-t border-slate-100 pt-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-2 rounded-full border border-slate-200 px-2 py-1 self-start">
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
            <div class="text-left sm:text-right">
                <div v-if="line.compare_at_price && line.compare_at_price > line.price" class="text-xs text-slate-400 line-through">
                    {{ displayCompareAtLine }}
                </div>
                <div class="text-sm font-semibold text-slate-900">
                    {{ displayTotalPrice }}
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useTranslations } from '@/i18n'
import { computed } from 'vue'
import { usePromoNow, formatCountdown } from '@/composables/usePromoCountdown.js'
import { useUserPreferences } from '@/composables/useUserPreferences.js'

const props = defineProps({
    line: { type: Object, required: true },
    currency: { type: String, default: 'USD' },
    promotions: { type: Array, default: () => [] },
})

defineEmits(['remove', 'update'])

const { currentCurrency, formatCurrency, convertCurrency } = useUserPreferences()
const displayCurrency = computed(() => currentCurrency.value || props.currency)

const displayUnitPrice = computed(() =>
    formatCurrency(
        convertCurrency(Number(props.line.price ?? 0), 'USD', displayCurrency.value),
        displayCurrency.value
    )
)
const displayPrice = (amount) =>
    formatCurrency(
        convertCurrency(Number(amount ?? 0), 'USD', displayCurrency.value),
        displayCurrency.value
    )

const displayTotalPrice = computed(() => {
    return formatCurrency(
        convertCurrency(Number((props.line.price ?? 0) * (props.line.quantity ?? 1)), 'USD', displayCurrency.value),
        displayCurrency.value
    )
})
const displayCompareAtLine = computed(() =>
    formatCurrency(
        convertCurrency(Number((props.line.compare_at_price ?? 0) * (props.line.quantity ?? 1)), 'USD', displayCurrency.value),
        displayCurrency.value
    )
)
const lineSavings = computed(() => {
    const compareAt = Number(props.line.compare_at_price ?? 0)
    const price = Number(props.line.price ?? 0)
    const quantity = Number(props.line.quantity ?? 1)
    if (compareAt <= price) return 0
    return (compareAt - price) * quantity
})
const stockState = computed(() => {
    const stock = Number(props.line.stock_on_hand ?? 0)
    if (!Number.isFinite(stock)) return { label: '', class: '' }
    if (stock <= 0) return { label: t('Out of stock'), class: 'bg-rose-50 text-rose-700' }
    if (stock <= 3) return { label: t('Only :count left', { count: stock }), class: 'bg-amber-50 text-amber-700' }
    return { label: t('In stock'), class: 'bg-emerald-50 text-emerald-700' }
})

const { t } = useTranslations()
const now = usePromoNow()
const linePromotion = computed(() => {
    if (!props.promotions?.length) return null
    return props.promotions.find(p =>
        (p.targets || []).some(t => {
            if (t.target_type === 'product') return t.target_id == props.line.product_id
            if (t.target_type === 'category') return t.target_id == props.line.category_id
            return false
        })
    )
})
const promoCountdown = computed(() => formatCountdown(linePromotion.value?.end_at, now.value))
</script>
