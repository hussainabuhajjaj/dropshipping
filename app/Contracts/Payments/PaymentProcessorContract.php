<?php

declare(strict_types=1);

namespace App\Contracts\Payments;

interface PaymentProcessorContract
{
    /** Initiate a payment for the given order and payment method. */
    public function initiate(object $order, string $method, ?string $returnUrl): array;

    /** Verify a payment by its provider reference. */
    public function verify(string $reference): array;

    /** Process a webhook payload from a payment provider. */
    public function processWebhook(array $payload): void;

    /** Refund a payment. */
    public function refund(string $reference, float $amount): array;
}
