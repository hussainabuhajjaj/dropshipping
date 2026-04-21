// Paystack configuration for frontend
window.paystackConfig = {
    korapayEnabled: @json(config('payments.korapay_enabled', false)),
    paystackEnabled: @json(config('payments.paystack_enabled', true)),
};
