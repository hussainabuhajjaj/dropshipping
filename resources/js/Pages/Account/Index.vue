<template>
  <StorefrontLayout>
    <div class="grid gap-8 lg:grid-cols-[260px,1fr]">
      <aside class="card-muted h-fit space-y-4 p-4">
        <h3 class="text-sm font-semibold text-slate-900">Account menu</h3>
        <nav class="space-y-2 text-sm text-slate-700">
          <button class="nav-link" @click="scrollTo('profile')">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5Zm-8 8a8 8 0 0 1 16 0"/></svg>
            <span>Profile & settings</span>
          </button>
          <button class="nav-link" @click="scrollTo('addresses')">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 9 12 4.5 20.25 9v7.5A2.25 2.25 0 0 1 18 18.75H6A2.25 2.25 0 0 1 3.75 16.5Z"/></svg>
            <span>Addresses</span>
          </button>
          <button class="nav-link" @click="scrollTo('payments')">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><rect x="3" y="5" width="18" height="14" rx="2" ry="2" stroke-width="1.5"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18"/></svg>
            <span>Payment methods</span>
          </button>
          <button class="nav-link" @click="scrollTo('orders')">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16m-9 4h9m-9 4h9M7 10h.01M7 14h.01M5 20h14a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1Z"/></svg>
            <span>Order history</span>
          </button>
          <Link class="nav-link" href="/account/notifications">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0a3 3 0 1 1-6 0"/></svg>
            <span>Notifications</span>
          </Link>
          <button class="nav-link" @click="scrollTo('refunds')">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.5 12a7.5 7.5 0 0 1 7.5-7.5h.75M4.5 12a7.5 7.5 0 0 0 7.5 7.5h.75M4.5 12H3m10.5 7.5V21m0-16.5V3M21 12h-4.5"/></svg>
            <span>Refunds</span>
          </button>
          <button class="nav-link" @click="scrollTo('tracking')">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m12 21-4.5-6a6 6 0 1 1 9 0Z"/><circle cx="12" cy="11" r="1.5"/></svg>
            <span>Tracking</span>
          </button>
          <button class="nav-link" @click="scrollTo('wallet')">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><rect x="3" y="7" width="18" height="12" rx="2" ry="2" stroke-width="1.5"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11h3m-3 4h3"/></svg>
            <span>Gift cards & coupons</span>
          </button>
        </nav>
      </aside>

      <div class="space-y-10">
      <section class="space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Account</p>
            <h1 class="text-3xl font-semibold tracking-tight text-slate-900">Hello, {{ user.name || 'Customer' }}</h1>
            <p class="text-sm text-slate-500">Manage your orders, payments, addresses, and wallet like Noon’s profile.</p>
          </div>
          <Link href="/products" class="btn-ghost">Continue shopping</Link>
        </div>

        <div class="grid gap-3 md:grid-cols-3">
          <Link href="/account/orders" class="quick-link">
            <div>
              <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Orders</p>
              <p class="text-base font-semibold text-slate-900">Order history</p>
            </div>
            <span class="text-xs text-slate-500">Track & reorder</span>
          </Link>
          <Link href="/account/addresses" class="quick-link">
            <div>
              <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Addresses</p>
              <p class="text-base font-semibold text-slate-900">Saved addresses</p>
            </div>
            <span class="text-xs text-slate-500">Default & delivery</span>
          </Link>
          <Link href="/account/notifications" class="quick-link">
            <div>
              <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Updates</p>
              <p class="text-base font-semibold text-slate-900">Notifications</p>
            </div>
            <span class="text-xs text-slate-500">Orders & alerts</span>
          </Link>
          <Link href="/orders/track" class="quick-link">
            <div>
              <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Tracking</p>
              <p class="text-base font-semibold text-slate-900">Track order</p>
            </div>
            <span class="text-xs text-slate-500">Live status</span>
          </Link>
          <a href="#payments" class="quick-link">
            <div>
              <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Payments</p>
              <p class="text-base font-semibold text-slate-900">Payment methods</p>
            </div>
            <span class="text-xs text-slate-500">Default card / MoMo</span>
          </a>
          <a href="#wallet" class="quick-link">
            <div>
              <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Wallet</p>
              <p class="text-base font-semibold text-slate-900">Gift cards & coupons</p>
            </div>
            <span class="text-xs text-slate-500">Redeem & save</span>
          </a>
          <Link href="/support" class="quick-link">
            <div>
              <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Support</p>
              <p class="text-base font-semibold text-slate-900">Help center</p>
            </div>
            <span class="text-xs text-slate-500">Chat & returns</span>
          </Link>
        </div>
      </section>

      <section id="profile" class="grid gap-6 lg:grid-cols-[1.4fr,0.6fr]">
        <div class="card space-y-6 p-6">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">Profile details</h2>
            <p class="text-sm text-slate-500">Update your name and email used for order updates.</p>
          </div>

          <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="updateProfile">
            <div>
              <label class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Name</label>
              <input v-model="profileForm.name" type="text" class="input-base mt-2 w-full" />
            </div>
            <div>
              <label class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Email</label>
              <input v-model="profileForm.email" type="email" class="input-base mt-2 w-full" />
            </div>
            <div class="sm:col-span-2">
              <button type="submit" class="btn-primary" :disabled="profileForm.processing">
                {{ profileForm.processing ? 'Saving...' : 'Save profile' }}
              </button>
            </div>
          </form>
        </div>

        <div class="card-muted space-y-4 p-6">
          <h3 class="text-sm font-semibold text-slate-900">Quick settings</h3>
          <div class="space-y-2 text-sm text-slate-600">
            <p>Orders and shipping updates are sent to {{ user.email }}.</p>
            <p>Need help? Reach support from the footer links anytime.</p>
            <p>
              Track orders from the tracking page.
              <Link href="/orders/track" class="text-blue-600 hover:underline">Track order</Link>
            </p>
          </div>
        </div>
      </section>

      <section id="addresses" class="grid gap-6 lg:grid-cols-2">
        <div class="card space-y-6 p-6">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">Saved addresses</h2>
            <p class="text-sm text-slate-500">Keep delivery and billing addresses ready.</p>
          </div>

          <div v-if="addresses.length" class="space-y-3">
            <div v-for="address in addresses" :key="address.id" class="rounded-xl border border-slate-100 p-4 text-sm">
              <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <p class="font-semibold text-slate-900">{{ address.name || 'Address' }}</p>
                  <p class="text-slate-500">
                    {{ address.line1 }}<span v-if="address.line2">, {{ address.line2 }}</span>
                  </p>
                  <p class="text-slate-500">
                    {{ address.city }}<span v-if="address.state">, {{ address.state }}</span>
                    <span v-if="address.postal_code"> {{ address.postal_code }}</span>
                  </p>
                  <p class="text-slate-500">Country: {{ address.country }}</p>
                </div>
                <button type="button" class="btn-ghost text-xs" @click="removeAddress(address.id)">Remove</button>
              </div>
            </div>
          </div>
          <div v-else class="text-sm text-slate-500">No saved addresses yet.</div>

          <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="addAddress">
            <input v-model="addressForm.name" type="text" placeholder="Full name" class="input-base" />
            <input v-model="addressForm.phone" type="text" placeholder="Phone" class="input-base" />
            <input v-model="addressForm.line1" type="text" placeholder="Address line 1" class="input-base sm:col-span-2" />
            <input v-model="addressForm.line2" type="text" placeholder="Address line 2" class="input-base sm:col-span-2" />
            <input v-model="addressForm.city" type="text" placeholder="City" class="input-base" />
            <input v-model="addressForm.state" type="text" placeholder="State" class="input-base" />
            <input v-model="addressForm.postal_code" type="text" placeholder="Postal code" class="input-base" />
            <input v-model="addressForm.country" type="text" placeholder="Country code" class="input-base" />
            <select v-model="addressForm.type" class="input-base sm:col-span-2">
              <option value="shipping">Shipping</option>
              <option value="billing">Billing</option>
            </select>
            <label class="flex items-center gap-2 text-xs text-slate-600 sm:col-span-2">
              <input v-model="addressForm.is_default" type="checkbox" class="rounded border-slate-300" />
              Set as default
            </label>
            <div class="sm:col-span-2">
              <button type="submit" class="btn-secondary" :disabled="addressForm.processing">
                {{ addressForm.processing ? 'Saving...' : 'Add address' }}
              </button>
            </div>
          </form>
        </div>

        <div id="payments" class="card space-y-6 p-6">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-lg font-semibold text-slate-900">Payment methods</h2>
              <p class="text-sm text-slate-500">Manage cards and wallets on the payments page.</p>
            </div>
            <Link href="/account/payments" class="btn-ghost text-xs">Manage</Link>
          </div>
          <div class="text-sm text-slate-500">Quickly jump to payments to add or edit your methods.</div>
        </div>
      </section>

      <section id="orders" class="grid gap-6 lg:grid-cols-2">
        <div class="card space-y-6 p-6">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h2 class="text-lg font-semibold text-slate-900">Order history</h2>
              <p class="text-sm text-slate-500">Track recent orders and re-open tracking links.</p>
            </div>
            <div class="flex items-center gap-2">
              <input v-model="orderSearch" type="text" placeholder="Search order #" class="input-base w-40" />
              <Link href="/account/orders" class="btn-ghost text-xs">See all</Link>
            </div>
          </div>

          <div v-if="flashSuccess" class="rounded-lg border border-green-100 bg-green-50 px-3 py-2 text-sm text-green-700">
            {{ flashSuccess }}
          </div>

          <div v-if="filteredOrders.length" class="space-y-3">
            <div v-for="order in filteredOrders" :key="order.id" class="rounded-xl border border-slate-100 p-4 text-sm">
              <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <p class="font-semibold text-slate-900">#{{ order.number }}</p>
                  <p class="text-slate-500">
                    {{ formatDate(order.placed_at) }} - {{ order.currency }} {{ Number(order.grand_total).toFixed(2) }}
                  </p>
                  <p class="text-slate-500">
                    Status: {{ order.status }} - Payment: {{ order.payment_status }}
                  </p>
                </div>
                <div class="flex flex-col gap-2">
                  <Link :href="`/orders/${order.id}`" class="btn-secondary px-3 py-2 text-xs">
                    View order
                  </Link>
                  <Link :href="`/orders/track?number=${order.number}`" class="btn-ghost text-xs text-blue-700">
                    Track
                  </Link>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="text-sm text-slate-500">No orders yet.</div>
        </div>

        <div id="refunds" class="card space-y-6 p-6">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-lg font-semibold text-slate-900">Refunds</h2>
              <p class="text-sm text-slate-500">Recent refunds issued to your payment provider.</p>
            </div>
            <Link href="/account/refunds" class="btn-ghost text-xs">View</Link>
          </div>
          <div class="text-sm text-slate-500">Open refunds page to see details.</div>
        </div>

        <div class="card space-y-4 p-6">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-lg font-semibold text-slate-900">Link past guest orders</h2>
              <p class="text-sm text-slate-500">Match guest orders by your account email and optional phone last 4.</p>
            </div>
          </div>

          <form class="space-y-3" @submit.prevent="claimOrders">
            <div>
              <label class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Email</label>
              <input v-model="claimOrdersForm.email" type="email" class="input-base mt-1 w-full" readonly />
              <p v-if="claimOrdersForm.errors.email" class="text-xs text-red-600">{{ claimOrdersForm.errors.email }}</p>
            </div>
            <div>
              <label class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Phone last 4 (optional)</label>
              <input v-model="claimOrdersForm.phone_last4" type="text" maxlength="4" class="input-base mt-1 w-full" placeholder="1234" />
              <p v-if="claimOrdersForm.errors.phone_last4" class="text-xs text-red-600">{{ claimOrdersForm.errors.phone_last4 }}</p>
            </div>
            <button type="submit" class="btn-secondary" :disabled="claimOrdersForm.processing">
              {{ claimOrdersForm.processing ? 'Linking...' : 'Link orders' }}
            </button>
            <p v-if="claimOrdersForm.hasErrors" class="text-xs text-red-600">Check the details above and try again.</p>
          </form>
        </div>
      </section>

      <section id="tracking" class="card space-y-4 p-6">
        <div>
          <h2 class="text-lg font-semibold text-slate-900">Tracking</h2>
          <p class="text-sm text-slate-500">Track any order by number or tracking code.</p>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
          <Link href="/orders/track" class="btn-secondary">Go to tracking</Link>
          <p class="text-sm text-slate-500">You can also open tracking links from each order.</p>
        </div>
      </section>

      <section id="wallet" class="card space-y-4 p-6">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">Wallet</h2>
            <p class="text-sm text-slate-500">Manage gift cards and coupons on the wallet page.</p>
          </div>
          <Link href="/account/wallet" class="btn-ghost text-xs">Open wallet</Link>
        </div>
        <div class="text-sm text-slate-500">Redeem gift cards and save coupons in wallet.</div>
      </section>
    </div>
  </div>
</StorefrontLayout>
</template>

<script setup>
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'

const props = defineProps({
  user: { type: Object, required: true },
  addresses: { type: Array, default: () => [] },
  paymentMethods: { type: Array, default: () => [] },
  orders: { type: Array, default: () => [] },
  refunds: { type: Array, default: () => [] },
  giftCards: { type: Array, default: () => [] },
  savedCoupons: { type: Array, default: () => [] },
  availableCoupons: { type: Array, default: () => [] },
})

const page = usePage()
const flashSuccess = computed(() => page.props.flash?.success || null)

const orderSearch = ref('')
const filteredOrders = computed(() => {
  const query = orderSearch.value.trim().toLowerCase()
  if (! query) return props.orders
  return props.orders.filter((order) => {
    return (
      order.number?.toLowerCase().includes(query) ||
      order.status?.toLowerCase().includes(query) ||
      order.payment_status?.toLowerCase().includes(query)
    )
  })
})

const profileForm = useForm({
  name: props.user.name || '',
  email: props.user.email || '',
})

const addressForm = useForm({
  name: '',
  phone: '',
  line1: '',
  line2: '',
  city: '',
  state: '',
  postal_code: '',
  country: 'CI',
  type: 'shipping',
  is_default: false,
})

const giftCardForm = useForm({
  code: '',
})

const couponForm = useForm({
  code: '',
})

const claimOrdersForm = useForm({
  email: props.user.email || '',
  phone_last4: '',
})

const updateProfile = () => {
  profileForm.patch('/profile', { preserveScroll: true })
}

const addAddress = () => {
  addressForm.post('/account/addresses', {
    preserveScroll: true,
    onSuccess: () => addressForm.reset(),
  })
}

const removeAddress = (id) => {
  router.delete(`/account/addresses/${id}`, { preserveScroll: true })
}

const addPaymentMethod = () => {
  paymentForm.post('/account/payment-methods', {
    preserveScroll: true,
    onSuccess: () => paymentForm.reset(),
  })
}

const removePaymentMethod = (id) => {
  router.delete(`/account/payment-methods/${id}`, { preserveScroll: true })
}

const redeemGiftCard = () => {
  giftCardForm.post('/account/gift-cards/redeem', {
    preserveScroll: true,
    onSuccess: () => giftCardForm.reset(),
  })
}

const saveCoupon = () => {
  couponForm.post('/account/coupons/save', {
    preserveScroll: true,
    onSuccess: () => couponForm.reset(),
  })
}

const claimOrders = () => {
  claimOrdersForm.post('/account/claim-orders', {
    preserveScroll: true,
    onSuccess: () => {
      claimOrdersForm.reset('phone_last4')
    },
  })
}

const removeCoupon = (id) => {
  router.delete(`/account/coupons/${id}`, { preserveScroll: true })
}

const formatDate = (value) => {
  if (! value) {
    return '-'
  }
  return new Date(value).toLocaleDateString()
}

const formatCoupon = (coupon) => {
  if (! coupon) {
    return ''
  }
  if (coupon.type === 'fixed') {
    return `Save ${coupon.amount}`
  }
  return `Save ${Number(coupon.amount).toFixed(0)}%`
}

const scrollTo = (id) => {
  const el = document.getElementById(id)
  if (el) {
    el.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }
}
</script>

<style scoped>
.nav-link {
  @apply flex w-full items-center gap-2 rounded-xl px-3 py-2 text-left transition hover:bg-white hover:shadow-sm;
}
</style>
