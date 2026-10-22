<template>
    <div v-if="card?.id"
         :key="card.id"
         @click="selectStoredCard(card)"
         class="flex items-center p-4 border rounded-xl cursor-pointer transition hover:border-[#f59e0b]"
         :class="{'border-[#f59e0b] bg-amber-50/50': is_selected}"
    >
        <input
            type="radio"
            name="card"
            :checked="is_selected"
            class="w-4 h-4 text-[#f59e0b]"
        >
        <div class="ml-3 flex items-center gap-3 flex-1">
            <!-- Card Icon -->
            <svg v-if="card.brand === 'visa'" class="h-8 w-auto" viewBox="0 0 48 16">
                <path fill="#1A1F71"
                      d="M17.1 15.3h-3.1l2.2-13.4h3.1l-2.2 13.4zm14.6-13.3c-.7-.3-1.8-.6-3.2-.6-3.5 0-6 1.8-6 4.4 0 1.9 1.8 3 3.1 3.6 1.4.6 1.9 1 1.9 1.5 0 .8-.9 1.2-1.8 1.2-1.2 0-1.9-.2-2.9-.7l-.4-.2-.5 3.1c.7.3 2 .6 3.4.6 3.6 0 6-1.8 6.1-4.5 0-1.5-.9-2.7-2.9-3.6-1.2-.6-2-1-2-1.6 0-.5.6-1.1 2-1.1 1.1 0 2 .2 2.6.5l.3.2.5-3zm6.2 7.3c.2-.6 1-3.2 1-3.2 0 .1.2-.6.3-.9l.2.8s.5 2.5.6 3.3h-2.1zm2.8-6.9h-2.4c-.7 0-1.3.2-1.6.9l-4.6 10.2h3.2l.6-1.7h4c.1.4.4 1.7.4 1.7h2.8l-2.4-11.1zm-21.4 0l-3 8.2-.3-1.4c-.4-1.4-1.8-3-3.4-3.8l2.2 8.4h3.2l4.8-11.4h-3.5z"/>
                <path fill="#F79F1A"
                      d="M8.3 1.9H.1L0 2.6c6.4 1.6 10.7 5.5 12.5 10.2l-1.8-8.6c-.3-1.2-1.2-1.9-2.4-2.3z"/>
            </svg>
            <svg v-else-if="card.brand === 'mastercard'" class="h-8 w-auto" viewBox="0 0 36 22">
                <circle cx="13" cy="11" r="8" fill="#EB001B" opacity="0.8"/>
                <circle cx="23" cy="11" r="8" fill="#F79E1B" opacity="0.8"/>
            </svg>

            <div class="flex-1">
                <p class="font-medium text-slate-900">{{ card.number }}</p>
                <p class="text-xs text-slate-500">{{ t('Expires') }}: {{ card.exp_date }}</p>
                <p class="text-xs text-slate-500">{{ t('Holder') }}: {{ card.nickname }}</p>
            </div>

            <button
                @click.stop="removeStoredCard"
                class="text-slate-400 hover:text-rose-600 transition"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </button>
        </div>
    </div>
</template>
<script setup>
import Swal from 'sweetalert2'

import {computed} from "vue";
import axios from "axios";
import {toastAlert} from "@/utils/toast.js";
import {router} from '@inertiajs/vue3'
const emit = defineEmits(['select_card'])

const props = defineProps({
    card: {
        type: Object,
    },
    is_selected: {
        type: Boolean,
    },

})

const card = computed(() => props.card);
const is_selected = computed(() => props.is_selected);
const selectStoredCard = () => {
    emit('select_card');
}

const t = (key, params = {}) => {
    const translations = {
        'Payment Methods': 'Payment Methods',
        'Your saved cards': 'Your saved cards',
        'Expires': 'Expires',
        'Add new card': 'Add new card',
        'Enter new card details': 'Enter new card details',
        'Card Number': 'Card Number',
        'Expiry Date': 'Expiry Date',
        'CVV': 'CVV',
        'Cardholder Name': 'Cardholder Name',
        'Save this card for future payments': 'Save this card for future payments',
        'Bank Transfer Details': 'Bank Transfer Details',
        'Please transfer the total amount to the following bank account:': 'Please transfer the total amount to the following bank account:',
        'Bank': 'Bank',
        'Account Name': 'Account Name',
        'Account Number': 'Account Number',
        'IBAN': 'IBAN',
        'Amount': 'Amount',
        'Reference': 'Reference',
        'Please include the reference number in your transfer. Your order will be processed once payment is confirmed.': 'Please include the reference number in your transfer. Your order will be processed once payment is confirmed.',
        'Upload Payment Receipt (Optional)': 'Upload Payment Receipt (Optional)',
        'Choose File': 'Choose File',
        'I Have Transferred the Amount': 'I Have Transferred the Amount',
        'Mobile Number': 'Mobile Number',
        'Mobile Money PIN': 'Mobile Money PIN',
        'Pay with :provider': `Pay with ${params.provider || ''}`,
        'Pay': 'Pay',
        'Processing...': 'Processing...',
        'Your payment information is secure and encrypted': 'Your payment information is secure and encrypted'
    }
    let text = translations[key] || key
    if (params) {
        Object.keys(params).forEach(param => {
            text = text.replace(`:${param}`, params[param])
        })
    }
    return text
}
const removeStoredCard = async () => {
    // make request to remove
    Swal.fire({
        title: 'Are you sure about this process?',
        icon: 'question',
        showCloseButton: true,
        showCancelButton: true,
        confirmButtonText: 'Yes',
        cancelButtonText: 'No',
        confirmButtonColor: '#56ace0',
        allowOutsideClick: false
    }).then(function (result) {
        if (result.value) {
            router.delete(`/account/payment-methods/${card.value.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    toastAlert('success', 'Card Deleted Successfully');
                    location.reload();
                }
            })
        }
    });
}

</script>
