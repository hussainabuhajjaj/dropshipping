<template>
    <div v-if="loading" class="flex items-center justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-t-blue-500">
            <div class="border-t-blue-200 border-transparent border-t-4 h-8 w-8"></div>
        </div>
        <div class="text-center">
            <p class="text-sm text-gray-600">{{ message }}</p>
        </div>
    </div>
    
    <div v-else-if="error" class="text-center py-4">
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md">
            <div class="text-sm font-medium">{{ error }}</div>
        </div>
    </div>
    
    <div v-else-if="success" class="text-center py-4">
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-md">
            <div class="text-sm font-medium">{{ successMessage }}</div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const props = defineProps({
    loading: Boolean,
    error: String,
    success: Boolean,
    message: String,
    successMessage: String,
})

onMounted(() => {
    if (ref.value) {
        verifyPayment()
    }
})

const verifyPayment = async () => {
    loading.value = true
    error.value = ''
    success.value = false
    message.value = 'Verifying payment status...'
    
    try {
        const response = await fetch(`/api/payments/verify?reference=${ref.value}`)
        const data = await response.json()
        
        if (data.success) {
            success.value = true
            message.value = data.message
            successMessage.value = `Payment confirmed! Order: ${data.data.order_number}`
            
            // Redirect to order confirmation after delay
            setTimeout(() => {
                window.location.href = `/orders/confirmation/${data.data.order_number}`
            }, 2000)
        } else {
            error.value = data.message || 'Payment verification failed'
        }
    } catch (err) {
        error.value = 'Verification failed. Please try again.'
        console.error('Verification error:', err)
    } finally {
        loading.value = false
    }
}
</script>
