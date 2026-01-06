<template>
  <div class="bg-white rounded-lg shadow p-6 border-l-4" :class="statusBorderClass">
    <div class="flex items-start justify-between mb-4">
      <div>
        <p class="text-sm font-medium text-gray-500 uppercase tracking-wide">Order Status</p>
        <h3 class="text-2xl font-bold text-gray-900 mt-1">{{ statusLabel }}</h3>
      </div>
      <div class="text-3xl" :class="statusIconClass">{{ statusIcon }}</div>
    </div>

    <p class="text-gray-600 leading-relaxed mb-6">{{ statusExplanation }}</p>

    <div v-if="showTrackingInfo" class="pt-4 border-t border-gray-200">
      <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Tracking Info</p>
      <div class="bg-gray-50 rounded px-3 py-2">
        <p v-if="trackingNumber" class="font-mono text-sm text-gray-900">{{ trackingNumber }}</p>
        <a
          v-if="trackingUrl"
          :href="trackingUrl"
          target="_blank"
          rel="noopener noreferrer"
          class="text-blue-600 hover:text-blue-800 text-sm font-medium inline-flex items-center mt-1"
        >
          Track Package →
        </a>
      </div>
    </div>

    <div v-if="showRefundInfo" class="pt-4 border-t border-gray-200">
      <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Refund Status</p>
      <div class="bg-amber-50 rounded px-3 py-2 border border-amber-200">
        <p class="text-sm text-amber-900 font-medium">
          {{ refundAmount ? `Refund: ${displayRefundAmount}` : 'Refund pending' }}
        </p>
        <p v-if="refundNotes" class="text-sm text-amber-800 mt-1">{{ refundNotes }}</p>
      </div>
    </div>

    <div v-if="showTimelineHint" class="pt-4 border-t border-gray-200">
      <p class="text-xs text-gray-500">
        <span v-if="statusKey === 'received'">📦 Received by us</span>
        <span v-else-if="statusKey === 'dispatched'">✈️ Shipped from supplier</span>
        <span v-else-if="statusKey === 'in_transit'">🚚 In transit to you</span>
        <span v-else-if="statusKey === 'out_for_delivery'">📍 Out for delivery today</span>
        <span v-else-if="statusKey === 'delivered'">✅ Delivered</span>
        <span v-else-if="statusKey === 'issue_detected'">⚠️ Issue detected</span>
        <span v-else-if="statusKey === 'refunded'">💰 Refunded</span>
      </p>
    </div>
  </div>
</template>

<script setup>
import { convertCurrency, formatCurrency } from '@/utils/currency.js'

const displayRefundAmount = computed(() => {
  // Assume refund is in USD unless a currency prop is added
  return formatCurrency(convertCurrency(props.refundAmount ?? 0, 'USD', 'USD'), 'USD')
})
import { computed } from 'vue'

const props = defineProps({
  orderStatus: {
    type: String,
    required: true,
    // One of: 'received', 'dispatched', 'in_transit', 'out_for_delivery', 'delivered', 'issue_detected', 'refunded', 'cancelled'
  },
  trackingNumber: {
    type: String,
    default: null,
  },
  trackingUrl: {
    type: String,
    default: null,
  },
  refundAmount: {
    type: Number,
    default: null,
  },
  refundNotes: {
    type: String,
    default: null,
  },
  refundedAt: {
    type: String,
    default: null,
  },
})

const statusKey = computed(() => props.orderStatus || 'received')

const statusLabel = computed(() => {
  const labels = {
    received: 'Order Received',
    dispatched: 'Dispatched',
    in_transit: 'In Transit',
    out_for_delivery: 'Out for Delivery',
    delivered: 'Delivered',
    issue_detected: 'Issue Detected',
    refunded: 'Refunded',
    cancelled: 'Cancelled',
  }
  return labels[statusKey.value] || 'Unknown Status'
})

const statusExplanation = computed(() => {
  const explanations = {
    received:
      'We've received your order and are preparing it for shipment. You'll receive a tracking number once it ships.',
    dispatched:
      'Your order has shipped from our supplier and is on its way! Check back soon for a tracking number.',
    in_transit:
      'Your package is traveling to the delivery center. You can track it using the tracking number below.',
    out_for_delivery: 'Your package is out for delivery today. You should receive it by end of business.',
    delivered: 'Your package has been delivered! We hope you enjoy your purchase.',
    issue_detected:
      'We detected an issue with your order. A member of our team will contact you with next steps.',
    refunded: 'Your order has been refunded. The amount will appear in your account within 3-5 business days.',
    cancelled: 'Your order has been cancelled. If this was unexpected, please contact support.',
  }
  return explanations[statusKey.value] || 'Your order is being processed.'
})

const statusIcon = computed(() => {
  const icons = {
    received: '📦',
    dispatched: '✈️',
    in_transit: '🚚',
    out_for_delivery: '📍',
    delivered: '✅',
    issue_detected: '⚠️',
    refunded: '💰',
    cancelled: '❌',
  }
  return icons[statusKey.value] || '❓'
})

const statusBorderClass = computed(() => {
  const classes = {
    received: 'border-blue-400',
    dispatched: 'border-purple-400',
    in_transit: 'border-orange-400',
    out_for_delivery: 'border-green-400',
    delivered: 'border-green-500',
    issue_detected: 'border-red-400',
    refunded: 'border-amber-400',
    cancelled: 'border-gray-400',
  }
  return classes[statusKey.value] || 'border-gray-400'
})

const statusIconClass = computed(() => {
  const classes = {
    received: 'text-blue-500',
    dispatched: 'text-purple-500',
    in_transit: 'text-orange-500',
    out_for_delivery: 'text-green-500',
    delivered: 'text-green-600',
    issue_detected: 'text-red-500',
    refunded: 'text-amber-500',
    cancelled: 'text-gray-500',
  }
  return classes[statusKey.value] || 'text-gray-500'
})

const showTrackingInfo = computed(() => props.trackingNumber || props.trackingUrl)
const showRefundInfo = computed(() => props.refundAmount || props.refundedAt)
const showTimelineHint = computed(() => ['received', 'dispatched', 'in_transit', 'out_for_delivery'].includes(statusKey.value))
</script>
