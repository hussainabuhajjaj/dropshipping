<?php

declare(strict_types=1);

namespace App\Infrastructure\Payments\Paystack;

use App\Domain\Payments\Models\Payment;
use App\Services\Api\ApiClient;
use App\Services\Api\ApiException;
use App\Services\Api\ApiResponse;
use RuntimeException;

class PaystackRefundService
{
    private ApiClient $client;

    public function __construct()
    {
        $config = config('services.paystack', []);
        $secret = (string) ($config['secret_key'] ?? '');

        if ($secret === '') {
            throw new RuntimeException('Paystack secret key is not configured.');
        }

        $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://api.paystack.co'), '/');
        $this->client = (new ApiClient($baseUrl, ['Accept' => 'application/json']))->withToken($secret);
    }

    /**
     * Initiate a refund for a payment
     * 
     * @param Payment $payment The payment to refund
     * @param float|null $amount Amount to refund (null for full refund)
     * @param string|null $reason Reason for refund
     * @param int $userId User initiating the refund
     * @return ApiResponse
     * @throws ApiException
     */
    public function refund(Payment $payment, ?float $amount = null, ?string $reason = null, int $userId): ApiResponse
    {
        if ($payment->status !== 'paid') {
            throw new RuntimeException('Only paid payments can be refunded.');
        }

        if ($payment->provider !== 'paystack') {
            throw new RuntimeException('This service only handles Paystack refunds.');
        }

        if (!$payment->provider_reference) {
            throw new RuntimeException('Payment reference is missing.');
        }

        // Default to full refund if no amount specified
        $refundAmount = $amount ?? (float) $payment->amount;
        
        // Validate refund amount
        $alreadyRefunded = (float) ($payment->refunded_amount ?? 0);
        $availableToRefund = (float) $payment->amount - $alreadyRefunded;
        
        if ($refundAmount > $availableToRefund) {
            throw new RuntimeException("Cannot refund {$refundAmount}. Only {$availableToRefund} available.");
        }

        if ($refundAmount <= 0) {
            throw new RuntimeException('Refund amount must be greater than zero.');
        }

        // Paystack expects amount in kobo (smallest currency unit)
        $amountInKobo = (int) round($refundAmount * 100);

        $payload = [
            'transaction' => $payment->provider_reference,
            'amount' => $amountInKobo,
        ];

        if ($reason) {
            $payload['merchant_note'] = $reason;
        }

        try {
            $response = $this->client->post('/refund', $payload);
            $unwrapped = $this->unwrap($response);

            // Update payment record
            $this->updatePaymentRecord($payment, $refundAmount, $unwrapped, $reason, $userId);

            return $unwrapped;
        } catch (ApiException $e) {
            // Log the error but still throw it
            \Log::error('Paystack refund failed', [
                'payment_id' => $payment->id,
                'amount' => $refundAmount,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Check refund status
     */
    public function checkRefundStatus(string $reference): ApiResponse
    {
        $response = $this->client->get('/refund/' . urlencode($reference));
        return $this->unwrap($response);
    }

    /**
     * Update payment record after successful refund
     */
    private function updatePaymentRecord(Payment $payment, float $amount, ApiResponse $response, ?string $reason, int $userId): void
    {
        $data = $response->data;
        $refundReference = is_array($data) ? ($data['id'] ?? null) : null;

        $newRefundedAmount = (float) ($payment->refunded_amount ?? 0) + $amount;
        $isFullRefund = $newRefundedAmount >= (float) $payment->amount;

        $payment->update([
            'refunded_amount' => $newRefundedAmount,
            'refund_status' => $isFullRefund ? 'full' : 'partial',
            'refund_reference' => $refundReference,
            'refund_reason' => $reason,
            'refunded_by' => $userId,
            'refunded_at' => now(),
            'status' => $isFullRefund ? 'refunded' : 'paid',
        ]);

        // Update order payment status
        if ($payment->order) {
            $payment->order->update([
                'payment_status' => $isFullRefund ? 'refunded' : 'paid',
                'status' => $isFullRefund ? 'refunded' : $payment->order->status,
            ]);
        }
    }

    /**
     * Unwrap Paystack API response
     */
    private function unwrap(ApiResponse $response): ApiResponse
    {
        $payload = is_array($response->data) ? $response->data : [];
        $status = (bool) ($payload['status'] ?? false);

        if (!$status) {
            $message = is_array($payload) ? ($payload['message'] ?? 'Refund API error') : 'Refund API error';
            throw new ApiException($message, $response->status, null, $payload);
        }

        return ApiResponse::success($payload['data'] ?? null, $payload, $payload['message'] ?? null, $response->status);
    }
}
