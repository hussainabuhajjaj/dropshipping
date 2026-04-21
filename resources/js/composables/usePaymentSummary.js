import { computed } from 'vue'

export function usePaymentSummary(summaryData) {
    // Format price helper
    const formatPrice = (amount, currency = 'USD') => {
        if (amount === null || amount === undefined) return ''

        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency,
        }).format(amount)
    }

    // Main summary computed
    const paymentSummary = computed(() => {
        if (!summaryData.value) return null

        const data = summaryData.value

        return {
            // Raw values
            raw: {
                subtotal: data.subtotal,
                shipping: data.shipping,
                discount: data.discount,
                tax_total: data.tax_total,
                total: data.total,
                currency: data.currency
            },

            // Formatted values for display
            formatted: {
                subtotal: formatPrice(data.subtotal, data.currency),
                shipping: formatPrice(data.shipping, data.currency),
                discount: formatPrice(data.discount, data.currency),
                tax_total: formatPrice(data.tax_total, data.currency),
                total: formatPrice(data.total, data.currency)
            },

            // Labels and metadata
            labels: {
                discount: data.discount_label || 'Discount',
                tax: data.tax_label || 'Tax',
                shipping_method: data.shipping_method || 'standard'
            },

            // Tax information
            tax: {
                included: data.tax_included || false,
                label: data.tax_label || 'VAT',
                total: data.tax_total,
                formatted: formatPrice(data.tax_total, data.currency)
            },

            // Discount information
            discount: {
                amount: data.discount,
                formatted: formatPrice(data.discount, data.currency),
                label: data.discount_label,
                appliedPromotions: data.appliedPromotions || [],
                cartPromotions: data.cartPromotions || [],
                coupon: data.coupon
            },

            // Shipping information
            shipping: {
                cost: data.shipping,
                formatted: formatPrice(data.shipping, data.currency),
                method: data.shipping_method || 'standard',
                method_label: getShippingMethodLabel(data.shipping_method)
            },

            // Cart requirements
            requirements: data.minimum_cart_requirement ? {
                passes: data.minimum_cart_requirement.passes,
                threshold: data.minimum_cart_requirement.threshold,
                effective_total: data.minimum_cart_requirement.effective_total,
                message: data.minimum_cart_requirement.message,
                remaining: Math.max(0, data.minimum_cart_requirement.threshold - data.minimum_cart_requirement.effective_total),
                formatted_threshold: formatPrice(data.minimum_cart_requirement.threshold, data.currency),
                formatted_effective: formatPrice(data.minimum_cart_requirement.effective_total, data.currency),
                formatted_remaining: formatPrice(
                    Math.max(0, data.minimum_cart_requirement.threshold - data.minimum_cart_requirement.effective_total),
                    data.currency
                )
            } : null,

            // Summary items for display (useful for v-for loops)
            items: [
                {
                    id: 'subtotal',
                    label: 'Subtotal',
                    amount: data.subtotal,
                    formatted: formatPrice(data.subtotal, data.currency),
                    type: 'subtotal'
                },
                {
                    id: 'shipping',
                    label: 'Shipping',
                    amount: data.shipping,
                    formatted: formatPrice(data.shipping, data.currency),
                    type: 'shipping',
                    method: data.shipping_method
                },
                ...(data.discount > 0 ? [{
                    id: 'discount',
                    label: data.discount_label || 'Discount',
                    amount: -data.discount,
                    formatted: `-${formatPrice(data.discount, data.currency)}`,
                    type: 'discount'
                }] : []),
                {
                    id: 'tax',
                    label: data.tax_included ? `${data.tax_label || 'Tax'} (included)` : (data.tax_label || 'Tax'),
                    amount: data.tax_total,
                    formatted: formatPrice(data.tax_total, data.currency),
                    type: 'tax',
                    included: data.tax_included
                },
                {
                    id: 'total',
                    label: 'Total',
                    amount: data.total,
                    formatted: formatPrice(data.total, data.currency),
                    type: 'total',
                    is_total: true
                }
            ],

            // Helpers
            hasDiscount: data.discount > 0,
            hasShipping: data.shipping > 0,
            hasTax: data.tax_total > 0,
            hasRequirements: !!data.minimum_cart_requirement,

            // Currency info
            currency: data.currency,
            currency_symbol: getCurrencySymbol(data.currency)
        }
    })

    // Helper to get shipping method label
    const getShippingMethodLabel = (method) => {
        const labels = {
            'standard': 'Standard Shipping',
            'express': 'Express Shipping',
            'overnight': 'Overnight Shipping',
            'pickup': 'Store Pickup'
        }
        return labels[method] || method || 'Shipping'
    }

    // Helper to get currency symbol
    const getCurrencySymbol = (currency) => {
        const symbols = {
            'USD': '$',
            'EUR': '€',
            'GBP': '£',
            'JOD': 'JD',
            'NGN': '₦',
            'GHS': '₵',
            'KES': 'KSh'
        }
        return symbols[currency] || currency
    }

    // Method to check if free shipping is available
    const checkFreeShippingEligibility = (summary) => {
        if (!summary.requirements) return false
        return summary.requirements.passes && summary.shipping > 0
    }

    return {
        paymentSummary,
        formatPrice,
        checkFreeShippingEligibility
    }
}
