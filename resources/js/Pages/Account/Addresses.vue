<template>
  <StorefrontLayout>
    <div class="space-y-8">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Account</p>
          <h1 class="text-3xl font-semibold tracking-tight text-slate-900">Your addresses</h1>
        </div>
        <Link href="/account" class="btn-ghost text-sm">Back to profile</Link>
      </div>

      <section class="grid gap-6 lg:grid-cols-2">
        <div class="card space-y-6 p-6">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">Saved addresses</h2>
            <p class="text-sm text-slate-500">Choose your default address or add/remove as needed.</p>
          </div>

          <div v-if="addresses.length" class="space-y-3">
            <div v-for="address in addresses" :key="address.id" class="rounded-xl border border-slate-100 p-4 text-sm">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p class="font-semibold text-slate-900">
                    {{ address.name || 'Address' }}
                    <span
                      v-if="address.is_default"
                      class="ml-2 inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[0.7rem] font-semibold text-emerald-700"
                    >
                      <svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                      </svg>
                      Default
                    </span>
                  </p>
                  <p class="text-slate-500">
                    {{ address.line1 }}<span v-if="address.line2">, {{ address.line2 }}</span>
                  </p>
                  <p class="text-slate-500">
                    {{ address.city }}<span v-if="address.state">, {{ address.state }}</span>
                    <span v-if="address.postal_code"> {{ address.postal_code }}</span>
                  </p>
                  <p class="text-slate-500">Country: {{ address.country }}</p>
                  <p class="text-slate-400 text-xs">Type: {{ address.type }}</p>
                </div>
                <div class="flex items-center gap-2">
                  <button type="button" class="btn-secondary text-xs" @click="editAddress(address)">Edit</button>
                  <button
                    v-if="! address.is_default"
                    type="button"
                    class="btn-secondary text-xs"
                    @click="setDefault(address.id)"
                  >
                    Set default
                  </button>
                  <button type="button" class="btn-ghost text-xs" @click="removeAddress(address.id)">Remove</button>
                </div>
              </div>
            </div>
          </div>
          <EmptyState
            v-else
            variant="compact"
            eyebrow="Addresses"
            title="No saved addresses"
            message="Add a default shipping address to speed up checkout."
          />
        </div>

        <!-- Edit Address Form -->
        <div v-if="showEditForm" class="card space-y-4 p-6">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">Edit address</h2>
            <p class="text-sm text-slate-500">Update your address information below.</p>
          </div>
          <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="updateAddress">
            <input v-model="editForm.name" type="text" :placeholder="t('Full name')" class="input-base sm:col-span-2" />
            <input v-model="editForm.phone" type="text" :placeholder="t('Phone')" class="input-base sm:col-span-2" />
            <input v-model="editForm.line1" type="text" :placeholder="t('Address line 1')" class="input-base sm:col-span-2" />
            <input v-model="editForm.line2" type="text" :placeholder="t('Address line 2')" class="input-base sm:col-span-2" />
            <input v-model="editForm.city" type="text" :placeholder="t('City')" class="input-base" />
            <input v-model="editForm.state" type="text" :placeholder="t('State')" class="input-base" />
            <input v-model="editForm.postal_code" type="text" :placeholder="t('Postal code')" class="input-base" />
            <input v-model="editForm.country" type="text" :placeholder="t('Country code')" class="input-base" />
            <select v-model="editForm.type" class="input-base sm:col-span-2">
              <option value="shipping">{{ t('Shipping') }}</option>
              <option value="billing">{{ t('Billing') }}</option>
            </select>
            <label class="flex items-center gap-2 text-xs text-slate-600 sm:col-span-2">
              <input v-model="editForm.is_default" type="checkbox" class="rounded border-slate-300" />
              Set as default
            </label>
            <div class="sm:col-span-2 flex gap-3">
              <button type="submit" class="btn-primary flex-1" :disabled="editForm.processing">
                {{ editForm.processing ? 'Updating...' : 'Update address' }}
              </button>
              <button type="button" class="btn-secondary flex-1" @click="cancelEdit">Cancel</button>
            </div>
          </form>
        </div>

        <div v-if="!showEditForm" class="card space-y-4 p-6">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">Add new address</h2>
            <p class="text-sm text-slate-500">{{ t('New addresses appear at the top and become default') }}</p>
          </div>
          <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="addAddress">
            <input v-model="addressForm.name" type="text" :placeholder="t('Full name')" class="input-base sm:col-span-2" />
            <input v-model="addressForm.phone" type="text" :placeholder="t('Phone')" class="input-base sm:col-span-2" />
            <input v-model="addressForm.line1" type="text" :placeholder="t('Address line 1')" class="input-base sm:col-span-2" />
            <input v-model="addressForm.line2" type="text" :placeholder="t('Address line 2')" class="input-base sm:col-span-2" />
            <input v-model="addressForm.city" type="text" :placeholder="t('City')" class="input-base" />
            <input v-model="addressForm.state" type="text" :placeholder="t('State')" class="input-base" />
            <input v-model="addressForm.postal_code" type="text" :placeholder="t('Postal code')" class="input-base" />
            <input v-model="addressForm.country" type="text" :placeholder="t('Country code')" class="input-base" />
            <select v-model="addressForm.type" class="input-base sm:col-span-2">
              <option value="shipping">{{ t('Shipping') }}</option>
              <option value="billing">{{ t('Billing') }}</option>
            </select>
            <label class="flex items-center gap-2 text-xs text-slate-600 sm:col-span-2">
              <input v-model="addressForm.is_default" type="checkbox" class="rounded border-slate-300" />
              Set as default
            </label>
            <div class="sm:col-span-2">
              <button type="submit" class="btn-primary w-full" :disabled="addressForm.processing">
                {{ addressForm.processing ? 'Saving...' : 'Save address' }}
              </button>
            </div>
          </form>
        </div>
      </section>
    </div>
  </StorefrontLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { useTranslations } from '@/i18n'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import EmptyState from '@/Components/EmptyState.vue'

const props = defineProps({
  addresses: { type: Array, default: () => [] },
  user: { type: Object, default: null },
})

const { t } = useTranslations()

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

const editForm = useForm({
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

const editingAddress = ref(null)
const showEditForm = ref(false)

const addAddress = () => {
  addressForm.post('/account/addresses', {
    preserveScroll: true,
    onSuccess: () => addressForm.reset(),
  })
}

const removeAddress = (id) => {
  router.delete(`/account/addresses/${id}`, { preserveScroll: true })
}

const setDefault = (id) => {
  router.put(
    `/account/addresses/${id}`,
    { is_default: true },
    { preserveScroll: true, onSuccess: () => showToast('Default address updated') }
  )
}

const editAddress = (address) => {
  editingAddress.value = address
  editForm.name = address.name || ''
  editForm.phone = address.phone || ''
  editForm.line1 = address.line1 || ''
  editForm.line2 = address.line2 || ''
  editForm.city = address.city || ''
  editForm.state = address.state || ''
  editForm.postal_code = address.postal_code || ''
  editForm.country = address.country || 'CI'
  editForm.type = address.type || 'shipping'
  editForm.is_default = address.is_default || false
  showEditForm.value = true
}

const updateAddress = () => {
  if (!editingAddress.value) return
  
  editForm.put(`/account/addresses/${editingAddress.value.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      showEditForm.value = false
      editingAddress.value = null
      editForm.reset()
      showToast('Address updated successfully')
    }
  })
}

const cancelEdit = () => {
  showEditForm.value = false
  editingAddress.value = null
  editForm.reset()
}

const showToast = (message) => {
  if (window?.toast) {
    window.toast.success(message)
  }
}
</script>
