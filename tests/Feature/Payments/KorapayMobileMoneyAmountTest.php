<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Domain\Common\Models\Address;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\PaymentService;
use App\Infrastructure\Payments\Clients\KorapayClient;
use App\Services\Api\ApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KorapayMobileMoneyAmountTest extends TestCase
{
    use RefreshDatabase;

    public function test_initialize_korapay_mobile_money_converts_usd_total_to_xof_and_persists_charged_totals(): void
    {
        config([
            'currency.base' => 'USD',
            'currency.rates.USD_XOF' => 600,
            'currency.decimals.XOF' => 0,
        ]);

        $address = Address::create([
            'name' => 'Test Buyer',
            'phone' => '+22500000000',
            'line1' => '123 Test Street',
            'city' => 'Abidjan',
            'country' => 'CI',
            'type' => 'shipping',
        ]);

        $order = Order::query()->create([
            'number' => 'DS-TESTXOF',
            'email' => 'buyer@example.com',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 51.86,
            'shipping_total' => 0,
            'tax_total' => 0,
            'discount_total' => 0,
            'grand_total' => 51.86,
            'shipping_address_id' => $address->id,
            'billing_address_id' => $address->id,
            'placed_at' => now(),
        ]);

        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'provider' => 'korapay',
            'status' => 'pending',
            'provider_reference' => 'ref_test_123',
            'amount' => 51.86,
            'currency' => 'USD',
            'meta' => [],
        ]);

        $fake = new class extends KorapayClient {
            public array $lastPayload = [];

            public function __construct()
            {
                // no-op: override KorapayClient's config-based constructor
            }

            public function initialize(array $payload): ApiResponse
            {
                $this->lastPayload = $payload;

                return ApiResponse::success([
                    'reference' => $payload['reference'] ?? 'ref_unknown',
                    'checkout_url' => 'https://pay.test/checkout',
                ]);
            }
        };

        $this->app->instance(KorapayClient::class, $fake);

        /** @var PaymentService $service */
        $service = $this->app->make(PaymentService::class);

        $result = $service->initializeKorapay(
            order: $order,
            payment: $payment,
            customer: ['email' => $order->email, 'name' => 'Buyer'],
            method: 'mobile_money',
            returnUrl: 'https://example.test/return'
        );

        $this->assertSame('https://pay.test/checkout', $result['checkout_url']);
        $this->assertSame('ref_test_123', $result['reference']);

        // 51.86 USD * 600 = 31116 XOF (integer, no decimals)
        $this->assertSame('XOF', $fake->lastPayload['currency']);
        $this->assertSame(31116.0, (float) $fake->lastPayload['amount']);

        $payment = $payment->refresh();
        $this->assertSame('XOF', $payment->currency);
        $this->assertSame(31116.0, (float) $payment->amount);

        $this->assertSame(51.86, (float) ($payment->meta['order_amount'] ?? 0));
        $this->assertSame('USD', (string) ($payment->meta['order_currency'] ?? ''));
        $this->assertSame(31116.0, (float) ($payment->meta['charged_amount'] ?? 0));
        $this->assertSame('XOF', (string) ($payment->meta['charged_currency'] ?? ''));
    }
}

