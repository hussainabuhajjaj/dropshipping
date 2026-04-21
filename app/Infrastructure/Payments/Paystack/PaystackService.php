<?php

declare(strict_types=1);

namespace App\Infrastructure\Payments\Paystack;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Services\Api\ApiClient;
use App\Services\Api\ApiException;
use App\Services\Api\ApiResponse;
use RuntimeException;

class PaystackService
{
    private const BASE_URL = 'https://api.paystack.co';
    private const SUPPORTED_CURRENCY = 'XOF';
    private const MOBILE_MONEY_PROVIDERS = ['orange', 'wave', 'mtn'];

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

    public function publicKey(): string
    {
        return (string) config('services.paystack.public_key', '');
    }

    /* -------------------------------------------------
     | INIT ENTRY POINT
     |-------------------------------------------------*/
    public function initialize(
        Order $order,
        Payment $payment,
        array $customer = [],
        string $method = 'card',
        ?string $returnUrl = null
    ): ApiResponse {
        return $method === 'mobile_money'
            ? $this->initializeMobileMoneyRedirectTransaction($order, $payment, $customer, $returnUrl)
            : $this->initializeRedirectTransaction($order, $payment, $customer, $returnUrl);
    }

    public function initializeTransaction(
        ?Order $order,
        Payment $payment,
        string $email,
        string $name,
        ?string $returnUrl = null
    ): array {
        $response = $this->initializeRedirectTransaction($order, $payment, [
            'email' => $email,
            'name' => $name,
        ], $returnUrl);

        $data = is_array($response->data) ? $response->data : [];

        return [
            'authorization_url' => $data['authorization_url'] ?? null,
            'access_code' => $data['access_code'] ?? null,
            'reference' => $data['reference'] ?? $payment->provider_reference,
        ];
    }

    public function initializeMobileMoneyTransaction(
        ?Order $order,
        Payment $payment,
        string $email,
        string $name,
        ?string $phone = null,
        ?string $provider = null
    ): array {
        $response = $this->initializeMobileMoneyRedirectTransaction($order, $payment, [
            'email' => $email,
            'name' => $name,
            'phone' => $phone,
            'mobile_provider' => $provider,
        ], null);

        $data = is_array($response->data) ? $response->data : [];

        return [
            'authorization_url' => $data['authorization_url'] ?? null,
            'access_code' => $data['access_code'] ?? null,
            'reference' => $data['reference'] ?? $payment->provider_reference,
        ];
    }

    public function chargeMobileMoney(
        ?Order $order,
        Payment $payment,
        string $email,
        string $name,
        string $phone,
        ?string $provider = null
    ): array {
        $response = $this->createMobileMoneyCharge($order, $payment, [
            'email' => $email,
            'name' => $name,
            'phone' => $phone,
            'mobile_provider' => $provider,
        ]);

        return is_array($response->data) ? $response->data : [];
    }

    /* -------------------------------------------------
     | CARD PAYMENT (REDIRECT)
     |-------------------------------------------------*/
    private function initializeRedirectTransaction(
        ?Order $order,
        Payment $payment,
        array $customer,
        ?string $returnUrl
    ): ApiResponse {
        $reference = $this->safeReference($payment);

        $email = trim((string) ($customer['email'] ?? $order?->email ?? ''));
        if ($email === '') {
            throw new RuntimeException('Customer email is required.');
        }

        $currency = self::SUPPORTED_CURRENCY;

        $payload = [
            'email' => $email,
            'amount' => $this->normalizePaystackAmount($payment->amount, $currency),
            'currency' => $currency,
            'reference' => $reference,
            'callback_url' => $returnUrl ?: route('payments.paystack.callback'),
            'channels' => ['card'],
            'metadata' => $this->buildMetadata($order, $payment, [
                'payment_method' => 'card',
                'customer_name' => (string) ($customer['name'] ?? 'Customer'),
            ]),
        ];

        $result = $this->unwrap($this->client->post('/transaction/initialize', $payload));
        $data = is_array($result->data) ? $result->data : [];

        // Debug logging
        Log::error('Paystack initialize response', [
            'payload' => $payload,
            'response_data' => $data,
            'raw_response' => $result->raw,
            'status' => $result->status,
            'message' => $result->message,
        ]);

        $authorizationUrl = (string) ($data['authorization_url'] ?? '');

        // ✅ FIX: flexible validation (no hardcoded domain)
        if ($authorizationUrl === '' || ! filter_var($authorizationUrl, FILTER_VALIDATE_URL)) {
            Log::error('Paystack authorization URL validation failed', [
                'authorization_url' => $authorizationUrl,
                'is_empty' => $authorizationUrl === '',
                'is_valid_url' => filter_var($authorizationUrl, FILTER_VALIDATE_URL) !== false,
                'full_response' => $data,
            ]);
            throw new RuntimeException('Paystack did not return a valid authorization URL.');
        }

        return ApiResponse::success([
            'authorization_url' => $authorizationUrl,
            'access_code' => $data['access_code'] ?? null,
            'reference' => $data['reference'] ?? $reference,
        ], $result->raw, $result->message, $result->status);
    }

    private function initializeMobileMoneyRedirectTransaction(
        ?Order $order,
        Payment $payment,
        array $customer,
        ?string $returnUrl
    ): ApiResponse {
        $reference = $this->safeReference($payment);

        $email = trim((string) ($customer['email'] ?? $order?->email ?? ''));
        if ($email === '') {
            throw new RuntimeException('Customer email is required.');
        }

        $payload = [
            'email' => $email,
            'amount' => $this->normalizePaystackAmount($payment->amount, self::SUPPORTED_CURRENCY),
            'currency' => self::SUPPORTED_CURRENCY,
            'reference' => $reference,
            'callback_url' => $returnUrl ?: route('payments.paystack.callback'),
            'channels' => ['mobile_money'],
            'metadata' => $this->buildMetadata($order, $payment, [
                'payment_method' => 'mobile_money',
                'customer_name' => (string) ($customer['name'] ?? 'Customer'),
            ]),
        ];

        $result = $this->unwrap($this->client->post('/transaction/initialize', $payload));
        $data = is_array($result->data) ? $result->data : [];

        // Debug logging
        Log::error('Paystack mobile money initialize response', [
            'payload' => $payload,
            'response_data' => $data,
            'raw_response' => $result->raw,
            'status' => $result->status,
            'message' => $result->message,
        ]);

        $authorizationUrl = (string) ($data['authorization_url'] ?? '');

        if ($authorizationUrl === '' || ! filter_var($authorizationUrl, FILTER_VALIDATE_URL)) {
            Log::error('Paystack mobile money authorization URL validation failed', [
                'authorization_url' => $authorizationUrl,
                'is_empty' => $authorizationUrl === '',
                'is_valid_url' => filter_var($authorizationUrl, FILTER_VALIDATE_URL) !== false,
                'full_response' => $data,
            ]);
            throw new RuntimeException('Paystack did not return a valid authorization URL.');
        }

        return ApiResponse::success([
            'authorization_url' => $authorizationUrl,
            'access_code' => $data['access_code'] ?? null,
            'reference' => $data['reference'] ?? $reference,
        ], $result->raw, $result->message, $result->status);
    }

    /* -------------------------------------------------
     | MOBILE MONEY
     |-------------------------------------------------*/
    private function createMobileMoneyCharge(
        ?Order $order,
        Payment $payment,
        array $customer
    ): ApiResponse {
        $reference = $this->safeReference($payment);

        $email = trim((string) ($customer['email'] ?? $order?->email ?? ''));
        $phone = trim((string) ($customer['phone'] ?? ''));

        if ($email === '') {
            throw new RuntimeException('Customer email is required.');
        }

        if ($phone === '') {
            throw new RuntimeException('Phone number is required.');
        }

        $provider = $this->resolveMobileMoneyProvider($customer['mobile_provider'] ?? null);
        $currency = self::SUPPORTED_CURRENCY;

        $payload = [
            'email' => $email,
            'amount' => $this->normalizePaystackAmount($payment->amount, $currency),
            'currency' => $currency,
            'reference' => $reference,
            'mobile_money' => [
                'phone' => $phone,
                'provider' => $provider,
            ],
            'metadata' => $this->buildMetadata($order, $payment, [
                'payment_method' => 'mobile_money',
                'mobile_provider' => $provider,
                'phone' => $phone,
                'customer_name' => (string) ($customer['name'] ?? 'Customer'),
            ]),
        ];

        $result = $this->unwrap($this->client->post('/charge', $payload));
        $data = is_array($result->data) ? $result->data : [];

        return ApiResponse::success(
            $this->normalizeChargeData($data),
            $result->raw,
            $result->message,
            $result->status
        );
    }

    /* -------------------------------------------------
     | OTP
     |-------------------------------------------------*/
    public function submitOtp(string $reference, string $otp): ApiResponse
    {
        if (trim($otp) === '') {
            throw new RuntimeException('OTP is required.');
        }

        $result = $this->unwrap($this->client->post('/charge/submit_otp', [
            'reference' => $reference,
            'otp' => $otp,
        ]));

        return ApiResponse::success(
            $this->normalizeChargeData(is_array($result->data) ? $result->data : []),
            $result->raw,
            $result->message,
            $result->status
        );
    }

    public function submitMobileMoneyOtp(string $reference, ?string $otp = null): array
    {
        $response = $this->submitOtp($reference, (string) $otp);
        return is_array($response->data) ? $response->data : [];
    }

    /* -------------------------------------------------
     | VERIFY
     |-------------------------------------------------*/
    public function verify(string $reference): ApiResponse
    {
        $result = $this->unwrap($this->client->get('/transaction/verify/' . urlencode($reference)));

        $data = is_array($result->data) ? $result->data : [];

        return ApiResponse::success(
            $this->normalizeVerificationData($data),
            $result->raw,
            $result->message,
            $result->status
        );
    }

    public function verifyTransaction(string $reference): array
    {
        $response = $this->verify($reference);
        return is_array($response->data) ? $response->data : [];
    }

    /* -------------------------------------------------
     | WEBHOOK SECURITY
     |-------------------------------------------------*/
    public function validateWebhook(string $payload, string $signature): bool
    {
        $secret = (string) config('services.paystack.secret_key', '');

        if ($secret === '') {
            return false;
        }

        $computed = hash_hmac('sha512', $payload, $secret);

        return hash_equals($computed, $signature);
    }

    /* -------------------------------------------------
     | HELPERS
     |-------------------------------------------------*/

    private function safeReference(Payment $payment): string
    {
        if (!$payment->provider_reference) {
            throw new RuntimeException('Missing payment reference.');
        }

        return $payment->provider_reference;
    }

    private function normalizePaystackAmount(float|int|string|null $amount, string $currency): int
    {
        if (!is_numeric($amount)) {
            throw new RuntimeException('Invalid amount.');
        }

        $amount = (float) $amount;

        return (int) round($amount * 100);
    }

    private function buildMetadata(?Order $order, Payment $payment, array $extra = []): array
    {
        $metadata = [
            'payment_id' => $payment->id,
        ];
        
        if ($order) {
            $metadata = array_merge($metadata, [
                'order_number' => $order->number,
                'customer_id' => $order->customer_id,
            ]);
        }
        
        return array_merge($metadata, $extra);
    }

    private function resolveMobileMoneyProvider(mixed $provider): string
    {
        $value = strtolower(trim((string) $provider));

        if ($value === 'orange_money') {
            $value = 'orange';
        }

        if ($value === '') {
            throw new RuntimeException('Mobile money provider is required.');
        }

        if (! in_array($value, self::MOBILE_MONEY_PROVIDERS, true)) {
            throw new RuntimeException('Unsupported mobile money provider.');
        }

        return $value;
    }

    private function normalizeChargeData(array $data): array
    {
        $status = strtolower((string) ($data['status'] ?? 'pending'));

        return [
            'reference' => $data['reference'] ?? null,
            'status' => $status,
            'display_text' => $data['display_text'] ?? null,
            'message' => $data['display_text'] ?? $data['gateway_response'] ?? 'Charge attempted',
            'otp_required' => $status === 'send_otp',
            'pending_confirmation' => in_array($status, ['pay_offline', 'pending', 'processing', 'success'], true),
            'next_action' => $status === 'send_otp' ? 'submit_otp' : 'await_webhook',
        ];
    }

    private function normalizeVerificationData(array $data): array
    {
        return [
            'reference' => $data['reference'] ?? null,
            'status' => strtolower((string) ($data['status'] ?? 'pending')),
            'message' => $data['gateway_response'] ?? $data['message'] ?? null,
            'amount' => $data['amount'] ?? null,
            'currency' => $data['currency'] ?? self::SUPPORTED_CURRENCY,
        ];
    }

    private function assertConfigured(): void
    {
        $secret = (string) config('services.paystack.secret_key', '');
        $public = (string) config('services.paystack.public_key', '');

        if ($secret === '' || !str_starts_with($secret, 'sk_')) {
            throw new RuntimeException('Invalid Paystack secret key.');
        }

        if ($public === '' || !str_starts_with($public, 'pk_')) {
            throw new RuntimeException('Invalid Paystack public key.');
        }
    }

    private function unwrap(ApiResponse $response): ApiResponse
    {
        $raw = $response->raw;

        if (!($raw['status'] ?? false)) {
            throw new ApiException(
                $raw['message'] ?? 'Paystack error',
                $response->status,
                '',
                $raw
            );
        }

        return ApiResponse::success(
            $raw['data'] ?? null,
            $raw,
            $raw['message'] ?? '',
            $response->status
        );
    }
}
