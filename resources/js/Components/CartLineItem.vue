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
                <p v-if="linePromotion" class="text-[10px] font-semibold text-amber-700">
                    {{ linePromotion.name }}
                    <span v-if="linePromotion.value_type === 'percentage'">-{{ linePromotion.value }}%</span>
                    <span v-else-if="linePromotion.value_type === 'fixed'">-{{ displayPrice(linePromotion.value) }}</span>
                    <span v-if="promoCountdown" class="ml-1">· {{ t('Ends in') }} {{ promoCountdown }}</span>
                </p>
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
import { convertCurrency, formatCurrency } from '@/utils/currency.js'
import { useTranslations } from '@/i18n'
import { computed } from 'vue'
import { usePromoNow, formatCountdown } from '@/composables/usePromoCountdown.js'
import { useCurrency } from '@/composables/useCurrency.js'

const props = defineProps({
    line: { type: Object, required: true },
    currency: { type: String, default: 'USD' },
    promotions: { type: Array, default: () => [] },
})

defineEmits(['remove', 'update'])

const { selectedCurrency } = useCurrency()
const displayCurrency = computed(() => selectedCurrency.value || props.currency)

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
