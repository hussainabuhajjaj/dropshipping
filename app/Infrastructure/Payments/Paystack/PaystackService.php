<?php

declare(strict_types=1);

namespace App\Infrastructure\Payments\Paystack;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Services\Api\ApiClient;
use App\Services\Api\ApiException;
use App\Services\Api\ApiResponse;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PaystackService
{
    private const BASE_URL = 'https://api.paystack.co';
    private const SUPPORTED_CURRENCY = 'XOF';

    private ApiClient $client;

    public function __construct()
    {
        $this->assertConfigured();

        $this->client = new ApiClient(self::BASE_URL, [
            'Authorization' => 'Bearer ' . config('services.paystack.secret_key'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]);
    }

    public function initializeTransaction(
        Order $order,
        Payment $payment,
        string $email,
        string $name,
        ?string $returnUrl = null
    ): array {

        $reference = $this->paymentReference($payment);

        $payload = [
            'email' => $email,
            'amount' => $this->normalizeAmount($payment->amount),
            'currency' => self::SUPPORTED_CURRENCY,
            'reference' => $reference,

            // ✅ MUST allow both
            'channels' => ['card', 'mobile_money'],

            'callback_url' => $returnUrl ?: route('payments.paystack.callback'),

            'metadata' => [
                'order_number' => $order->number,
                'payment_id' => $payment->id,
                'customer_name' => $name,
            ],
        ];

        $response = $this->client->post('/transaction/initialize', $payload);

        // 🔥 LOG FULL RAW RESPONSE
        Log::info('PAYSTACK RAW RESPONSE', [
            'payload' => $payload,
            'raw' => $response->raw,
        ]);

        $raw = $response->raw;

        if (!($raw['status'] ?? false)) {
            throw new RuntimeException($raw['message'] ?? 'Paystack error');
        }

        $data = $raw['data'] ?? [];

        if (!isset($data['authorization_url'])) {
            throw new RuntimeException(
                'Paystack did not return authorization_url: ' . json_encode($raw)
            );
        }

        return [
            'authorization_url' => $data['authorization_url'],
            'reference' => $data['reference'] ?? $reference,
        ];
    }

    private function isMobileMoneyPayment(array $data): bool
    {
        // Mobile money payments typically don't return authorization URLs
        // They return status messages or require OTP verification
        return !isset($data['authorization_url']) ||
               (isset($data['message']) && str_contains(strtolower($data['message']), 'mobile money'));
    }

    public function verifyTransaction(string $reference): array
    {
        $response = $this->client->get('/transaction/verify/' . urlencode($reference));
        $result = $this->unwrap($response);

        $data = is_array($result->data) ? $result->data : [];

        return [
            'reference' => $data['reference'] ?? null,
            'status' => strtolower($data['status'] ?? 'pending'),
            'amount' => $data['amount'] ?? null,
            'currency' => $data['currency'] ?? null,
            'paid_at' => $data['paid_at'] ?? null,
        ];
    }

    public function normalizeAmount($amount): int
    {
        if (!is_numeric($amount)) {
            throw new RuntimeException('Amount must be numeric');
        }

        // Paystack expects amounts in cents/kobo (multiply by 100)
        $value = (int) round((float) $amount * 100);

        if ($value <= 0) {
            throw new RuntimeException('Invalid amount');
        }

        return $value;
    }

    private function paymentReference(Payment $payment): string
    {
        if (!$payment->provider_reference) {
            throw new RuntimeException('Missing payment reference');
        }

        return $payment->provider_reference;
    }

    private function unwrap(ApiResponse $response): ApiResponse
    {
        if (!($response->raw['status'] ?? false)) {
            throw new ApiException(
                $response->raw['message'] ?? 'Paystack error',
                $response->status
            );
        }

        return $response;
    }

    private function assertConfigured(): void
    {
        $secret = config('services.paystack.secret_key');
        $public = config('services.paystack.public_key');

        if (!$secret || !str_starts_with($secret, 'sk_')) {
            throw new RuntimeException('Invalid Paystack secret key');
        }

        if (!$public || !str_starts_with($public, 'pk_')) {
            throw new RuntimeException('Invalid Paystack public key');
        }
    }
}
