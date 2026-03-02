<template>
    <StorefrontLayout>
        <div class="max-w-7xl mx-auto">
            <h1 class="text-3xl font-bold text-slate-900 mb-8">Payment</h1>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left Column - Order Summary & Payment Methods -->
                <div class="lg:col-span-2 space-y-6">

                    <OrderSummary
                        :type="type"
                        :items="items"
                        :shipping="20"
                        :tax="6.01"
                        :discount="3.71"
                        discount-label="First order 10% off"
                        tax-label="VAT"
                        currency="USD"
                    />


                    <Address/>
                    <!-- Payment Methods Component -->
                    <PaymentMethods
                        :amount="final_total"
                        :currency="currency"
                        :initial-method="selectedMethod"
                        :is_processing="is_processing"
                        @method-change="handleMethodChange"
                        @pay-cards="payWithKorapay"
                    />

                </div>

                <!-- Right Column - Payment Summary & Error -->
                <div class="lg:col-span-1 space-y-6">
                    <PaymentSummary
                        :summary-data="summery"
                        :currency="currency"
                        :item-count="totalItems"
                        :estimated-delivery="estimatedDelivery"
                        :selected-method="selectedMethod"
                        :method-name="selectedMethodName"
                    />



                    <!-- Error Card Component (if payment fails) -->
                    <!--                    <ErrorCard-->
                    <!--                        v-if="paymentError"-->
                    <!--                        :error-message="errorMessage"-->
                    <!--                        :checkout-id="checkoutId"-->
                    <!--                        :timestamp="errorTimestamp"-->
                    <!--                        @contact-support="contactSupport"-->
                    <!--                    />-->
                </div>
            </div>
        </div>


    </StorefrontLayout>
</template>

<script setup>
import {ref, computed, onMounted} from 'vue'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import OrderSummary from '@/Components/payment/OrderSummary.vue'
import PaymentMethods from '@/Components/payment/PaymentMethods.vue'
import PaymentSummary from '@/Components/payment/PaymentSummary.vue'

import ErrorCard from '@/Components/common/ErrorCard.vue'
import {toastAlert} from "@/utils/toast.js";


import {usePage, router} from '@inertiajs/vue3'

const page = usePage();
import axios from "axios";
import Address from "@/Components/payment/Address.vue";


const type = page.props.type
const id = page.props.id
const summery = page.props.summery
const final_total = page.props.final_total
const items = computed(() => page.props.items)



const payment_result = page.props.payment_result

const successMessage = page.props.successMessage
// const errorMessage = page.props.errorMessage
// const errors = page.props.errors


// Mock order data
const orderItems = ref([
    {
        id: 1,
        name: 'Package 1',
        price: 200,
        quantity: 1,
        currency: 'USD',
        image: null
    }
])


// const shipping = ref(0)
// const tax = ref(0)
// const discount = ref(0)
const currency = ref('USD')
const is_processing = ref(false)

// const total = computed(() => {
//     return subtotal.value + shipping.value + tax.value - discount.value
// })

const totalItems = computed(() => {
    return items.value.reduce((sum, item) => sum + item.quantity, 0)
})

// Payment state
const selectedMethod = ref('card')
const selectedMethodName = ref('')
// const paymentStatus = ref('Pending')
const estimatedDelivery = ref('3-5 business days')

// Auth modal state
// const pendingPaymentData = ref(null)


// Error state
// const paymentError = ref(true) // Set to true to show error, false to hide
// const errorMessage = ref('Payment cannot be completed. Please contact support with following information:')
// const checkoutId = ref('IDBB407068825B84C90ABF544205412F.uato1-vm-tx02 is invalid.')
// const errorTimestamp = ref('Fri, 27 Feb 2026 18:51:38 GMT')

// Methods
const handleMethodChange = (method) => {
    selectedMethod.value = method
    const methodNames = {
        card: 'Visa , MasterCard',
        mobile_money: 'Mobile Payment',
    }
    selectedMethodName.value = methodNames[method] || method
}




const contactSupport = () => {
    window.location.href = 'mailto:support@example.com?subject=Payment Issue&body=' +
        encodeURIComponent(`Checkout ID: ${checkoutId.value}\nTimestamp: ${errorTimestamp.value}`)
}


const payWithKorapay = async (method) => {

    try {

        axios.post(`/pay/${type}/${id}/checkout` , {
            "method": method,
        }).then(({data}) => {
            console.log(data)
            if (data.status && data?.data?.redirect){
                console.log(123)
                window.location = data?.data?.redirect;
            }else{
                // error
            }
        });


    } catch (error) {
        console.error('Payment failed:', error)
        // emit('payment-failed', {
        //     message: error.message || 'Payment failed',
        //     provider: 'korapay'
        // })
    }
}

onMounted(() => {
    handleMethodChange(selectedMethod.value)
})

</script>
