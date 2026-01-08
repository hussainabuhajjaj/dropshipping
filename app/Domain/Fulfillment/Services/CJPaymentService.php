<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Services;

use App\Domain\Orders\Models\Order;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Domain\Fulfillment\Exceptions\FulfillmentException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CJPaymentService
{
    public function __construct(
        private readonly CJDropshippingClient $client,
    ) {}

    /**
     * Pay CJ balance for confirmed order.
     * 
     * Enforces: customer_paid → cj_created → cj_confirmed → cj_paid
     */
    public function payOrder(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            // ===== SAFETY CHECKS =====
            
            // 1. Customer must have paid
            if ($order->payment_status !== 'paid') {
                throw new FulfillmentException(
                    'Cannot pay CJ: customer payment not confirmed. Status: ' . $order->payment_status
                );
            }

            // 2. CJ order must exist
            if (!$order->cj_order_id) {
                throw new FulfillmentException('Cannot pay CJ: no CJ order ID created yet');
            }

            // 3. CJ order must be confirmed
            if ($order->cj_order_status !== 'confirmed') {
                throw new FulfillmentException(
                    'Cannot pay CJ: order not confirmed. Status: ' . $order->cj_order_status
                );
            }

            // ===== IDEMPOTENCY CHECK =====
            
            // Already paid - prevent double-payment
            if ($order->cj_payment_status === 'paid' && $order->cj_paid_at) {
                Log::warning('CJ already paid for this order, skipping', ['order_id' => $order->id]);
                return $order->fresh();
            }

            // Generate idempotency key if missing
            if (!$order->cj_payment_idempotency_key) {
                $order->update([
                    'cj_payment_idempotency_key' => Str::uuid(),
                ]);
            }

            // ===== PAYMENT EXECUTION =====
            
            try {
                // Get or generate payId
                $payId = $this->getOrGeneratePayId($order);

                Log::info('Paying CJ balance', [
                    'order_id' => $order->id,
                    'order_number' => $order->number,
                    'cj_order_id' => $order->cj_order_id,
                    'shipment_order_id' => $order->cj_shipment_order_id,
                    'pay_id' => $payId,
                    'amount' => $order->cj_amount_due,
                    'attempt' => $order->cj_payment_attempts + 1,
                ]);

                // Call CJ API
                $response = $this->client->payBalanceV2(
                    $order->cj_shipment_order_id,
                    $payId
                );

                $body = $this->validateResponse($response);

                // ===== SUCCESS =====
                
                $order->update([
                    'cj_payment_status' => 'paid',
                    'cj_paid_at' => now(),
                    'cj_payment_error' => null,
                    'cj_payment_attempts' => $order->cj_payment_attempts + 1,
                ]);

                Log::info('CJ payment succeeded', [
                    'order_id' => $order->id,
                    'order_number' => $order->number,
                    'response' => $body,
                ]);

                return $order->fresh();

            } catch (Throwable $e) {
                
                // ===== FAILURE =====
                
                $order->update([
                    'cj_payment_status' => 'failed',
                    'cj_payment_error' => $e->getMessage(),
                    'cj_payment_attempts' => $order->cj_payment_attempts + 1,
                ]);

                Log::error('CJ payment failed', [
                    'order_id' => $order->id,
                    'order_number' => $order->number,
                    'attempt' => $order->cj_payment_attempts,
                    'error' => $e->getMessage(),
                    'previous_error' => $order->cj_payment_error,
                ]);

                throw new FulfillmentException(
                    'CJ payment failed: ' . $e->getMessage(),
                    previous: $e
                );
            }
        });
    }

    /**
     * Get or generate payId for payment.
     */
    private function getOrGeneratePayId(Order $order): string
    {
        // If already have payId, return it (retry case)
        if ($order->cj_pay_id) {
            Log::debug('Using cached payId for order', [
                'order_id' => $order->id,
                'pay_id' => $order->cj_pay_id,
            ]);
            return $order->cj_pay_id;
        }

        Log::info('Generating parent order to get payId', [
            'order_id' => $order->id,
            'shipment_order_id' => $order->cj_shipment_order_id,
        ]);

        // Generate parent order and retrieve payId
        $response = $this->client->saveGenerateParentOrder($order->cj_shipment_order_id);
        $body = $this->validateResponse($response);

        $payId = $body['data']['payId'] ?? null;
        if (!$payId) {
            throw new FulfillmentException(
                'No payId returned from CJ saveGenerateParentOrder'
            );
        }

        // Cache payId for future retries
        $order->update(['cj_pay_id' => $payId]);

        Log::info('Generated payId for order', [
            'order_id' => $order->id,
            'pay_id' => $payId,
        ]);

        return $payId;
    }

    /**
     * Validate CJ API response.
     */
    private function validateResponse($response): array
    {
        if ($response->failed()) {
            throw new FulfillmentException('CJ API request failed: ' . $response->body());
        }

        $body = $response->json() ?? [];
        $code = $body['code'] ?? null;
        $message = $body['message'] ?? null;

        if ((int)$code !== 200) {
            throw new FulfillmentException(
                "CJ API error {$code}: {$message} - " . json_encode($body)
            );
        }

        return $body;
    }
}
