<template>
<!--    <section class="card p-5">-->
<!--        <h2 class="text-sm font-semibold text-slate-900">{{ t('Contact') }}</h2>-->
<!--        <div class="mt-4 grid gap-4 sm:grid-cols-2">-->
<!--            <input v-model="form.email" type="email" required :placeholder="t('Email')" class="input-base"/>-->
<!--            <input v-model="form.phone" type="tel" required :placeholder="t('Phone')" class="input-base"/>-->
<!--        </div>-->
<!--    </section>-->

    <!--    <section class="card p-5">-->
    <!--        <h2 class="text-sm font-semibold text-slate-900">{{ t('Shipping address') }}</h2>-->

    <!--        &lt;!&ndash; Address Selector &ndash;&gt;-->
    <!--        <div v-if="userAddresses.length > 0" class="mt-4">-->
    <!--            <label class="block text-sm font-medium text-slate-700 mb-2">-->
    <!--                {{ t('Choose an existing address') }}-->
    <!--            </label>-->
    <!--            <select-->
    <!--                v-model="selectedAddressId"-->
    <!--                @change="handleAddressSelection"-->
    <!--                class="input-base w-full"-->
    <!--            >-->
    <!--&lt;!&ndash;                <option value="">{{ t('Select an address...') }}</option>&ndash;&gt;-->
    <!--&lt;!&ndash;                <option value="new">{{ t('+ Add new address') }}</option>&ndash;&gt;-->
    <!--                <option-->
    <!--                    v-for="address in userAddresses"-->
    <!--                    :key="address.id"-->
    <!--                    :value="address.id"-->
    <!--                >-->
    <!--                    {{ formatAddressOption(address) }}-->
    <!--                </option>-->
    <!--            </select>-->
    <!--        </div>-->

    <!--        &lt;!&ndash; Address Form &ndash;&gt;-->
    <!--        <div v-if="selectedAddressId === 'new' || userAddresses.length === 0" class="mt-4">-->
    <!--            <div v-if="userAddresses.length > 0" class="mb-4">-->
    <!--                <h3 class="text-sm font-medium text-slate-700">{{ t('New address') }}</h3>-->
    <!--            </div>-->
    <!--            <div class="grid gap-4 sm:grid-cols-2">-->
    <!--                <input v-model="form.first_name" required :placeholder="t('First name')" class="input-base"/>-->
    <!--                <input v-model="form.last_name" :placeholder="t('Last name')" class="input-base"/>-->
    <!--                <input v-model="form.line1" required :placeholder="t('Address line 1')" class="input-base sm:col-span-2"/>-->
    <!--                <input v-model="form.line2" :placeholder="t('Address line 2')" class="input-base sm:col-span-2"/>-->
    <!--                <input v-model="form.city" required :placeholder="t('City')" class="input-base"/>-->
    <!--                <input v-model="form.state" :placeholder="t('State / Region')" class="input-base"/>-->
    <!--                <input v-model="form.postal_code" :placeholder="t('Postal code')" class="input-base"/>-->
    <!--                <input v-model="form.country" required :placeholder="t('Country')" class="input-base" disabled readonly/>-->
    <!--            </div>-->
    <!--        </div>-->

    <!--        &lt;!&ndash; Selected Address Display &ndash;&gt;-->
    <!--        <div v-if="selectedAddressId && selectedAddressId !== 'new'" class="mt-4 p-4 bg-slate-50 rounded-lg">-->
    <!--            <div class="flex justify-between items-start">-->
    <!--                <div>-->
    <!--                    <p class="font-medium text-slate-900">{{ selectedAddress?.name }}</p>-->
    <!--                    <p class="text-sm text-slate-600">{{ selectedAddress?.line1 }}</p>-->
    <!--                    <p v-if="selectedAddress?.line2" class="text-sm text-slate-600">{{ selectedAddress?.line2 }}</p>-->
    <!--                    <p class="text-sm text-slate-600">-->
    <!--                        {{ selectedAddress?.city }}{{ selectedAddress?.state ? `, ${selectedAddress?.state}` : '' }} {{ selectedAddress?.postal_code }}-->
    <!--                    </p>-->
    <!--                    <p class="text-sm text-slate-600">{{ selectedAddress?.country }}</p>-->
    <!--                </div>-->
    <!--&lt;!&ndash;                <button&ndash;&gt;-->
    <!--&lt;!&ndash;                    type="button"&ndash;&gt;-->
    <!--&lt;!&ndash;                    @click="selectedAddressId = 'new'"&ndash;&gt;-->
    <!--&lt;!&ndash;                    class="text-sm text-orange-600 hover:text-orange-700"&ndash;&gt;-->
    <!--&lt;!&ndash;                >&ndash;&gt;-->
    <!--&lt;!&ndash;                    {{ t('Change') }}&ndash;&gt;-->
    <!--&lt;!&ndash;                </button>&ndash;&gt;-->
    <!--            </div>-->
    <!--        </div>-->

    <!--        <textarea-->
    <!--            v-model="form.delivery_notes"-->
    <!--            rows="3"-->
    <!--            :placeholder="t('Delivery notes (optional)')"-->
    <!--            class="input-base mt-4 w-full"-->
    <!--        />-->
    <!--        <p class="mt-3 text-xs text-slate-500">-->
    <!--            {{-->
    <!--                t("Duties and VAT for Cote d'Ivoire are shown before payment. By placing the order you acknowledge customs may contact you if additional verification is required.")-->
    <!--            }}-->
    <!--        </p>-->
    <!--    </section>-->

    <section class="card p-5">
        <h2 class="text-sm font-semibold text-slate-900">{{ t('Shipping address') }}</h2>

        <!-- Address Selector -->
        <div v-if="userAddresses.length > 0" class="mt-4">
            <div class="flex items-center justify-between mb-2">
                <label class="block text-sm font-medium text-slate-700">
                    {{ t('Choose an existing address') }}
                </label>
                <button
                    v-if="!showNewAddressForm"
                    type="button"
                    @click="openNewAddressForm"
                    class="text-sm text-orange-600 hover:text-orange-700 font-medium flex items-center gap-1"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    {{ t('Add new address') }}
                </button>
            </div>

            <select
                v-model="selectedAddressId"
                @change="handleAddressSelection"
                class="input-base w-full"
                :disabled="showNewAddressForm"
            >
                <option value="">{{ t('Select an address...') }}</option>
                <option
                    v-for="address in userAddresses"
                    :key="address.id"
                    :value="address.id"
                >
                    {{ formatAddressOption(address) }}
                </option>
            </select>
        </div>

        <!-- Add New Address Button (when no addresses) -->
        <div v-else class="mt-4">
            <button
                type="button"
                @click="openNewAddressForm"
                class="w-full py-3 border-2 border-dashed border-slate-200 rounded-lg text-slate-600 hover:border-orange-300 hover:text-orange-600 transition flex items-center justify-center gap-2"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                {{ t('Add new address') }}
            </button>
        </div>

        <!-- New Address Form -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-2"
        >
            <div v-if="showNewAddressForm" class="mt-4 p-4 bg-slate-50 rounded-lg border border-slate-200">
                <!-- Form Header -->
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-medium text-slate-700">{{ t('New shipping address') }}</h3>
                    <button
                        type="button"
                        @click="cancelNewAddress"
                        class="text-slate-400 hover:text-slate-600 transition"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Form Fields -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs text-slate-600 mb-1">{{ t('First name') }} *</label>
                        <input
                            v-model="form.first_name"
                            required
                            :placeholder="t('First name')"
                            class="input-base w-full"
                        />
                    </div>

                    <div>
                        <label class="block text-xs text-slate-600 mb-1">{{ t('Last name') }}</label>
                        <input
                            v-model="form.last_name"
                            :placeholder="t('Last name')"
                            class="input-base w-full"
                        />
                    </div>

                    <div>
                        <label class="block text-xs text-slate-600 mb-1">{{ t('Email') }} *</label>
                        <input
                            v-model="form.email"
                            required
                            :placeholder="t('Email')"
                            class="input-base w-full"
                        />
                    </div>

                    <div>
                        <label class="block text-xs text-slate-600 mb-1">{{ t('Phone') }}</label>
                        <input
                            v-model="form.phone"
                            :placeholder="t('Phone')"
                            class="input-base w-full"
                        />
                    </div>


                    <div class="sm:col-span-2">
                        <label class="block text-xs text-slate-600 mb-1">{{ t('Address line 1') }} *</label>
                        <input
                            v-model="form.line1"
                            required
                            :placeholder="t('Street address, P.O. box')"
                            class="input-base w-full"
                        />
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs text-slate-600 mb-1">{{ t('Address line 2') }}</label>
                        <input
                            v-model="form.line2"
                            :placeholder="t('Apartment, suite, unit, etc.')"
                            class="input-base w-full"
                        />
                    </div>

                    <div>
                        <label class="block text-xs text-slate-600 mb-1">{{ t('City') }} *</label>
                        <input
                            v-model="form.city"
                            required
                            :placeholder="t('City')"
                            class="input-base w-full"
                        />
                    </div>

                    <div>
                        <label class="block text-xs text-slate-600 mb-1">{{ t('State / Region') }}</label>
                        <input
                            v-model="form.state"
                            :placeholder="t('State / Region')"
                            class="input-base w-full"
                        />
                    </div>

                    <div>
                        <label class="block text-xs text-slate-600 mb-1">{{ t('Postal code') }}</label>
                        <input
                            v-model="form.postal_code"
                            :placeholder="t('Postal code')"
                            class="input-base w-full"
                        />
                    </div>

                    <div>
                        <label class="block text-xs text-slate-600 mb-1">{{ t('Country') }} *</label>
                        <input
                            v-model="form.country"
                            required
                            :placeholder="t('Country')"
                            class="input-base w-full bg-slate-100"
                            disabled
                            readonly
                        />
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex gap-3 mt-4">
                    <button
                        type="button"
                        @click="saveNewAddress"
                        class="flex-1 bg-orange-600 text-white py-2 px-4 rounded-lg text-sm font-semibold hover:bg-orange-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="!isAddressValid"
                    >
                        {{ t('Save address') }}
                    </button>
                    <button
                        type="button"
                        @click="cancelNewAddress"
                        class="flex-1 bg-slate-200 text-slate-700 py-2 px-4 rounded-lg text-sm font-semibold hover:bg-slate-300 transition"
                    >
                        {{ t('Cancel') }}
                    </button>
                </div>
            </div>
        </Transition>

        <!-- Selected Address Display -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-2"
        >
            <div v-if="selectedAddress && !showNewAddressForm"
                 class="mt-4 p-4 bg-slate-50 rounded-lg border border-slate-200">
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-2">
                            <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <p class="font-medium text-slate-900">{{
                                    selectedAddress.name || t('Shipping address')
                                }}</p>
                        </div>
                        <p class="text-sm text-slate-600">{{ selectedAddress.line1 }}</p>
                        <p v-if="selectedAddress.line2" class="text-sm text-slate-600">{{ selectedAddress.line2 }}</p>
                        <p class="text-sm text-slate-600">
                            {{ selectedAddress.city }}{{ selectedAddress.state ? `, ${selectedAddress.state}` : '' }}
                            {{ selectedAddress.postal_code }}
                        </p>
                        <p class="text-sm text-slate-600">{{ selectedAddress.country }}</p>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Delivery Notes -->
        <textarea
            v-model="form.delivery_notes"
            rows="3"
            :placeholder="t('Delivery notes (optional)')"
            class="input-base mt-4 w-full"
        />

        <!-- Notice -->
        <p class="mt-3 text-xs text-slate-500">
            {{
                t("Duties and VAT for Cote d'Ivoire are shown before payment. By placing the order you acknowledge customs may contact you if additional verification is required.")
            }}
        </p>
    </section>


</template>

<script setup>
import {onMounted, ref, watch, computed} from "vue";
import {useTranslations} from "@/i18n.js";

const {t} = useTranslations()

const emit = defineEmits(['change-address'])

const props = defineProps({
    user: {type: Object, default: null},
    defaultAddress: {type: Object, default: null},
    userAddresses: {type: Array, default: () => []},
})


const selectedAddressId = ref(props.defaultAddress || '')
const showNewAddressForm = ref(false)


const form = ref({
    address_id: null,
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

const isAddressValid = computed(() => {
    return form.value.first_name &&
        form.value.line1 &&
        form.value.city &&
        form.value.country
})

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


// Computed property for selected address
const selectedAddress = computed(() => {
    if (!selectedAddressId.value) {
        return null
    }
    if ( selectedAddressId.value === 'new'){
        return form.value;
    }
    return props.userAddresses.find(addr => addr.id === selectedAddressId.value)
})

// Format address for dropdown option


const handleAddressSelection = (event) => {
    const addressId = event.target.value
    if (addressId) {
        selectedAddress.value = props.userAddresses.find(a => a.id == addressId)
        form.value.address_id = selectedAddress.value.id;
        emit('address-selected', selectedAddress.value)
    } else {
        selectedAddress.value = null
    }
}

const openNewAddressForm = () => {
    showNewAddressForm.value = true
    selectedAddressId.value = 'new'
    // Reset form
    form.value = {
        address_id: null,
        first_name: '',
        last_name: '',
        line1: '',
        line2: '',
        city: '',
        state: '',
        postal_code: '',
        country: props.country || "CI",
        delivery_notes: form.value.delivery_notes // Keep delivery notes
    }
}

const saveNewAddress = () => {
    if (!isAddressValid.value) return

    // Select the new address
    selectedAddress.value = form.value;
    selectedAddressId.value = "new";
    showNewAddressForm.value = false;
    emit('address-selected', form.value)
}


const cancelNewAddress = () => {
    showNewAddressForm.value = false
    selectedAddressId.value = ''
}


// Handle address selection
// const handleAddressSelection = () => {
//
//     if (selectedAddressId.value && selectedAddressId.value !== 'new') {
//         // const address = selectedAddress.value
//         // if (address) {
//             // Populate form with selected address data
//         console.log(form.value)
//             form.value.address_id = selectedAddressId.value;
//         console.log(form.value)
//             // form.value = {
//             //     ...form.value,
//             //     first_name: address.name?.split(' ')[0] || '',
//             //     last_name: address.name?.split(' ').slice(1).join(' ') || '',
//             //     line1: address.line1 || '',
//             //     line2: address.line2 || '',
//             //     city: address.city || '',
//             //     state: address.state || '',
//             //     postal_code: address.postal_code || '',
//             //     country: address.country || 'CI',
//             // }
//         // }
//     } else if (selectedAddressId.value === 'new') {
//         // Clear form for new address (keep email and phone)
//         const email = form.value.email
//         const phone = form.value.phone
//         form.value = {
//             email,
//             phone,
//             first_name: '',
//             last_name: '',
//             line1: '',
//             line2: '',
//             city: '',
//             state: '',
//             postal_code: '',
//             country: 'CI',
//             delivery_notes: '',
//         }
//     }
// }

onMounted(() => {
    form.value = {
        address_id: selectedAddressId?.value.id || null,
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
.input-base {
    @apply rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-900 placeholder-slate-400 focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500;
}

.card {
    @apply bg-white rounded-2xl shadow-sm border border-slate-200;
}
</style>
