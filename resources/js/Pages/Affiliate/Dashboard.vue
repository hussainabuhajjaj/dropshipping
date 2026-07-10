<template>
  <StorefrontLayout>
    <Head title="Affiliate Dashboard" />

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
      <div class="sm:flex sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Affiliate Dashboard</h1>
          <p class="mt-1 text-sm text-gray-500">Welcome back, {{ affiliate.name }}</p>
        </div>
        <span
          class="mt-2 inline-flex items-center rounded-full px-3 py-1 text-xs font-medium sm:mt-0"
          :class="statusClass"
        >
          {{ affiliate.status }}
        </span>
      </div>

      <div v-if="affiliate.status === 'pending'" class="mt-4 rounded-lg bg-yellow-50 p-4 text-sm text-yellow-800">
        Your account is pending approval. You'll be able to start earning commissions once approved.
      </div>

      <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl bg-white p-6 shadow-sm">
          <p class="text-sm font-medium text-gray-500">Available Balance</p>
          <p class="mt-2 text-3xl font-bold text-green-600">${{ formatNumber(affiliate.balance_available) }}</p>
        </div>
        <div class="rounded-xl bg-white p-6 shadow-sm">
          <p class="text-sm font-medium text-gray-500">Pending Balance</p>
          <p class="mt-2 text-3xl font-bold text-yellow-600">${{ formatNumber(affiliate.balance_pending) }}</p>
        </div>
        <div class="rounded-xl bg-white p-6 shadow-sm">
          <p class="text-sm font-medium text-gray-500">Total Earned</p>
          <p class="mt-2 text-3xl font-bold text-blue-600">${{ formatNumber(affiliate.total_earned) }}</p>
        </div>
        <div class="rounded-xl bg-white p-6 shadow-sm">
          <p class="text-sm font-medium text-gray-500">Commission Rate</p>
          <p class="mt-2 text-3xl font-bold text-purple-600">{{ (affiliate.commission_rate * 100).toFixed(1) }}%</p>
        </div>
      </div>

      <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="rounded-xl bg-white p-6 shadow-sm">
          <h2 class="text-lg font-semibold text-gray-900">Your Referral Link</h2>
          <p class="mt-1 text-sm text-gray-500">Share this link to earn commissions</p>
          <div class="mt-4 flex gap-2">
            <input
              ref="linkInput"
              :value="referral_link"
              class="block flex-1 rounded-lg border border-gray-300 bg-gray-50 px-4 py-2 text-sm text-gray-700"
              readonly
              @click="selectAll"
            />
            <button
              class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
              @click="copyLink"
            >
              {{ copied ? 'Copied!' : 'Copy' }}
            </button>
          </div>

          <h2 class="mt-6 text-lg font-semibold text-gray-900">Referral Coupon Code</h2>
          <p class="mt-1 text-sm text-gray-500">Customers get {{ referral_coupon.amount }}{{ referral_coupon.type === 'percent' ? '%' : '$' }} off</p>
          <div class="mt-2 flex gap-2">
            <input
              :value="referral_coupon.code"
              class="block flex-1 rounded-lg border border-gray-300 bg-gray-50 px-4 py-2 text-sm font-mono text-gray-700"
              readonly
              @click="selectAll"
            />
            <button
              class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
              @click="copyCouponCode"
            >
              {{ couponCopied ? 'Copied!' : 'Copy' }}
            </button>
          </div>

          <div class="mt-4 flex gap-2">
            <a
              :href="`https://wa.me/?text=${encodeURIComponent(shareText)}`"
              target="_blank"
              class="inline-flex items-center gap-1 rounded-lg bg-green-500 px-3 py-2 text-sm font-medium text-white hover:bg-green-600"
            >
              Share on WhatsApp
            </a>
            <a
              :href="`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(referral_link)}`"
              target="_blank"
              class="inline-flex items-center gap-1 rounded-lg bg-blue-500 px-3 py-2 text-sm font-medium text-white hover:bg-blue-600"
            >
              Share on Facebook
            </a>
            <a
              :href="`https://twitter.com/intent/tweet?text=${encodeURIComponent(shareText)}`"
              target="_blank"
              class="inline-flex items-center gap-1 rounded-lg bg-sky-500 px-3 py-2 text-sm font-medium text-white hover:bg-sky-600"
            >
              Share on X
            </a>
          </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm">
          <h2 class="text-lg font-semibold text-gray-900">Performance</h2>
          <div class="mt-4 grid grid-cols-2 gap-4">
            <div class="rounded-lg bg-gray-50 p-4 text-center">
              <p class="text-2xl font-bold text-gray-900">{{ stats.referral_count }}</p>
              <p class="text-sm text-gray-500">Total Referrals</p>
            </div>
            <div class="rounded-lg bg-gray-50 p-4 text-center">
              <p class="text-2xl font-bold text-gray-900">{{ stats.converted_referrals }}</p>
              <p class="text-sm text-gray-500">Converted</p>
            </div>
            <div class="rounded-lg bg-gray-50 p-4 text-center">
              <p class="text-2xl font-bold text-gray-900">{{ stats.approved_commissions }}</p>
              <p class="text-sm text-gray-500">Approved</p>
            </div>
            <div class="rounded-lg bg-gray-50 p-4 text-center">
              <p class="text-2xl font-bold text-gray-900">${{ formatNumber(stats.total_withdrawn) }}</p>
              <p class="text-sm text-gray-500">Withdrawn</p>
            </div>
          </div>

          <div class="mt-6">
            <h3 class="text-sm font-medium text-gray-900">Monthly Earnings</h3>
            <div class="mt-3 space-y-2">
              <div v-for="item in chart_data" :key="item.month" class="flex items-center gap-3">
                <span class="w-16 text-xs text-gray-500">{{ item.month }}</span>
                <div class="flex-1 rounded-full bg-gray-100">
                  <div
                    class="rounded-full bg-blue-500 py-1 text-center text-xs text-white transition-all"
                    :style="{ width: barWidth(item.earnings) + '%' }"
                  >
                    ${{ formatNumber(item.earnings) }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-8">
        <div class="rounded-xl bg-white p-6 shadow-sm">
          <div class="sm:flex sm:items-center sm:justify-between">
            <h2 class="text-lg font-semibold text-gray-900">Recent Commissions</h2>
          </div>
          <div v-if="recent_commissions.length === 0" class="mt-4 text-center text-sm text-gray-500 py-8">
            No commissions yet. Start sharing your referral link!
          </div>
          <div v-else class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Order</th>
                  <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Amount</th>
                  <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Rate</th>
                  <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                  <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Date</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200">
                <tr v-for="c in recent_commissions" :key="c.id" class="hover:bg-gray-50">
                  <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-900">{{ c.order_number }}</td>
                  <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-900">${{ formatNumber(c.commission_amount) }}</td>
                  <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{{ (c.commission_rate * 100).toFixed(1) }}%</td>
                  <td class="whitespace-nowrap px-4 py-3">
                    <span
                      class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                      :class="commissionStatusClass(c.status)"
                    >
                      {{ c.status }}
                    </span>
                  </td>
                  <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{{ c.created_at }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div v-if="affiliate.balance_available >= min_withdrawal" class="mt-6">
        <div class="rounded-xl bg-white p-6 shadow-sm">
          <h2 class="text-lg font-semibold text-gray-900">Request Withdrawal</h2>
          <form @submit.prevent="submitWithdrawal" class="mt-4 flex items-end gap-4">
            <div class="flex-1">
              <InputLabel for="withdrawal_amount" value="Amount ($)" />
              <TextInput
                id="withdrawal_amount"
                v-model="withdrawalForm.amount"
                type="number"
                step="0.01"
                class="mt-1 block w-full"
                :max="affiliate.balance_available"
                :min="min_withdrawal"
              />
              <InputError :message="withdrawalForm.errors.amount" class="mt-2" />
              <p class="mt-1 text-xs text-gray-500">
                Available: ${{ formatNumber(affiliate.balance_available) }} | Min: ${{ formatNumber(min_withdrawal) }}
              </p>
            </div>
            <PrimaryButton :disabled="withdrawalForm.processing" class="shrink-0">
              {{ withdrawalForm.processing ? 'Submitting...' : 'Request Withdrawal' }}
            </PrimaryButton>
          </form>
        </div>
      </div>
    </div>
  </StorefrontLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useForm, Head, Link } from '@inertiajs/vue3'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import InputError from '@/Components/InputError.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const props = defineProps({
  affiliate: { type: Object, required: true },
  referral_link: { type: String, required: true },
  referral_coupon: { type: Object, required: true },
  stats: { type: Object, required: true },
  chart_data: { type: Array, default: () => [] },
  recent_commissions: { type: Array, default: () => [] },
  min_withdrawal: { type: Number, default: 50 },
})

const linkInput = ref(null)
const copied = ref(false)
const couponCopied = ref(false)

const statusClass = computed(() => ({
  'bg-green-100 text-green-800': props.affiliate.status === 'approved',
  'bg-yellow-100 text-yellow-800': props.affiliate.status === 'pending',
  'bg-red-100 text-red-800': props.affiliate.status === 'suspended',
}))

const shareText = computed(() =>
  `Check out this store! Use my referral link to get ${props.referral_coupon.amount}% off: ${props.referral_link}`
)

function formatNumber(val) {
  return Number(val || 0).toFixed(2)
}

function selectAll(e) {
  e.target.select()
}

function copyLink() {
  navigator.clipboard.writeText(props.referral_link)
  copied.value = true
  setTimeout(() => { copied.value = false }, 2000)
}

function copyCouponCode() {
  navigator.clipboard.writeText(props.referral_coupon.code)
  couponCopied.value = true
  setTimeout(() => { couponCopied.value = false }, 2000)
}

const maxEarnings = computed(() => Math.max(...props.chart_data.map(d => d.earnings), 1))

function barWidth(earnings) {
  return Math.max((earnings / maxEarnings.value) * 100, 5)
}

function commissionStatusClass(status) {
  const map = {
    pending: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-blue-100 text-blue-800',
    rejected: 'bg-red-100 text-red-800',
    paid: 'bg-green-100 text-green-800',
  }
  return map[status] || 'bg-gray-100 text-gray-800'
}

const withdrawalForm = useForm({
  amount: props.min_withdrawal,
})

function submitWithdrawal() {
  withdrawalForm.post(route('affiliate.withdrawal.request'), {
    onSuccess: () => withdrawalForm.reset(),
  })
}
</script>
