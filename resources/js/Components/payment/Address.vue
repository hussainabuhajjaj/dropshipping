<template>
    <section class="card p-5">
        <h2 class="text-sm font-semibold text-slate-900">{{ t('Contact') }}</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <input v-model="form.email" type="email" required :placeholder="t('Email')" class="input-base"/>
            <input v-model="form.phone" type="tel" required :placeholder="t('Phone')" class="input-base"/>
        </div>
    </section>

    <section class="card p-5">
        <h2 class="text-sm font-semibold text-slate-900">{{ t('Shipping address') }}</h2>
        
        <!-- Address Selector -->
        <div v-if="userAddresses.length > 0" class="mt-4">
            <label class="block text-sm font-medium text-slate-700 mb-2">
                {{ t('Choose an existing address') }}
            </label>
            <select 
                v-model="selectedAddressId" 
                @change="handleAddressSelection"
                class="input-base w-full"
            >
                <option value="">{{ t('Select an address...') }}</option>
                <option value="new">{{ t('+ Add new address') }}</option>
                <option 
                    v-for="address in userAddresses" 
                    :key="address.id" 
                    :value="address.id"
                >
                    {{ formatAddressOption(address) }}
                </option>
            </select>
        </div>

        <!-- Address Form -->
        <div v-show="selectedAddressId === 'new' || userAddresses.length === 0" class="mt-4">
            <div v-if="userAddresses.length > 0" class="mb-4">
                <h3 class="text-sm font-medium text-slate-700">{{ t('New address') }}</h3>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <input v-model="form.first_name" required :placeholder="t('First name')" class="input-base"/>
                <input v-model="form.last_name" :placeholder="t('Last name')" class="input-base"/>
                <input v-model="form.line1" required :placeholder="t('Address line 1')" class="input-base sm:col-span-2"/>
                <input v-model="form.line2" :placeholder="t('Address line 2')" class="input-base sm:col-span-2"/>
                <input v-model="form.city" required :placeholder="t('City')" class="input-base"/>
                <input v-model="form.state" :placeholder="t('State / Region')" class="input-base"/>
                <input v-model="form.postal_code" :placeholder="t('Postal code')" class="input-base"/>
                <input v-model="form.country" required :placeholder="t('Country')" class="input-base" disabled readonly/>
            </div>
        </div>

        <!-- Selected Address Display -->
        <div v-if="selectedAddressId && selectedAddressId !== 'new'" class="mt-4 p-4 bg-slate-50 rounded-lg">
            <div class="flex justify-between items-start">
                <div>
                    <p class="font-medium text-slate-900">{{ selectedAddress?.name }}</p>
                    <p class="text-sm text-slate-600">{{ selectedAddress?.line1 }}</p>
                    <p v-if="selectedAddress?.line2" class="text-sm text-slate-600">{{ selectedAddress?.line2 }}</p>
                    <p class="text-sm text-slate-600">
                        {{ selectedAddress?.city }}{{ selectedAddress?.state ? `, ${selectedAddress?.state}` : '' }} {{ selectedAddress?.postal_code }}
                    </p>
                    <p class="text-sm text-slate-600">{{ selectedAddress?.country }}</p>
                </div>
                <button 
                    type="button" 
                    @click="selectedAddressId = 'new'"
                    class="text-sm text-orange-600 hover:text-orange-700"
                >
                    {{ t('Change') }}
                </button>
            </div>
        </div>

        <textarea
            v-model="form.delivery_notes"
            rows="3"
            :placeholder="t('Delivery notes (optional)')"
            class="input-base mt-4 w-full"
        />
        <p class="mt-3 text-xs text-slate-500">
            {{
                t("Duties and VAT for Cote d'Ivoire are shown before payment. By placing the order you acknowledge customs may contact you if additional verification is required.")
            }}
        </p>
    </section>

</template>

<script setup>
import {onMounted, ref, watch, computed} from "vue";

const emit = defineEmits(['change-address'])

const props = defineProps({
    user: {type: Object, default: null},
    defaultAddress: {type: Object, default: null},
    userAddresses: {type: Array, default: () => []},
    t: {type: Function, required: true},
})

const selectedAddressId = ref('')

const form = ref({
    email: null,
    phone: null,
    first_name: null,
    last_name: null,
    line1: null,
    line2: null,
    city: null,
    state: null,
    postal_code: null,
    country: null,
    delivery_notes: null,
})

// Computed property for selected address
const selectedAddress = computed(() => {
    if (!selectedAddressId.value || selectedAddressId.value === 'new') {
        return null
    }
    return props.userAddresses.find(addr => addr.id === selectedAddressId.value)
})

// Format address for dropdown option
const formatAddressOption = (address) => {
    const parts = [
        address.name,
        address.line1,
        address.city,
        address.state,
        address.postal_code
    ].filter(Boolean)
    
    return parts.join(', ')
}

// Handle address selection
const handleAddressSelection = () => {
    if (selectedAddressId.value && selectedAddressId.value !== 'new') {
        const address = selectedAddress.value
        if (address) {
            // Populate form with selected address data
            form.value = {
                ...form.value,
                first_name: address.name?.split(' ')[0] || '',
                last_name: address.name?.split(' ').slice(1).join(' ') || '',
                line1: address.line1 || '',
                line2: address.line2 || '',
                city: address.city || '',
                state: address.state || '',
                postal_code: address.postal_code || '',
                country: address.country || 'CI',
            }
        }
    } else if (selectedAddressId.value === 'new') {
        // Clear form for new address (keep email and phone)
        const email = form.value.email
        const phone = form.value.phone
        form.value = {
            email,
            phone,
            first_name: '',
            last_name: '',
            line1: '',
            line2: '',
            city: '',
            state: '',
            postal_code: '',
            country: 'CI',
            delivery_notes: '',
        }
    }
}

// Use the passed t function
const t = (key) => props.t(key)

onMounted(() => {
    form.value = {
        email: props.user?.email || '',
        phone: props.user?.phone || '',
        first_name: props.defaultAddress?.name?.split(' ')[0] || props.user?.name?.split(' ')[0] || '',
        last_name: props.defaultAddress?.name?.split(' ').slice(1).join(' ') || props.user?.name?.split(' ').slice(1).join(' ') || '',
        line1: props.defaultAddress?.line1 || '',
        line2: props.defaultAddress?.line2 || '',
        city: props.defaultAddress?.city || '',
        state: props.defaultAddress?.state || '',
        postal_code: props.defaultAddress?.postal_code || '',
        country: props.defaultAddress?.country || 'CI',
        delivery_notes: '',
    };
    
    // Set default address if provided
    if (props.defaultAddress && props.defaultAddress.id) {
        selectedAddressId.value = props.defaultAddress.id
    }
})

watch(form, () => {
    emit('change-address', form);
}, {deep: true})

</script>

<style scoped>

</style>
