<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutShippingQuoteTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_returns_cj_shipping_quote_for_checkout()
    {
        // Simulate cart with CJ product
        $cart = [[
            'product_id' => 1,
            'variant_id' => 1,
            'cj_pid' => 'test_pid',
            'cj_vid' => 'test_vid',
            'quantity' => 2,
            'price' => 10.0,
            'currency' => 'USD',
        ]];
        session(['cart' => $cart]);

        $address = [
            'name' => 'John Doe',
            'line1' => '123 Main St',
            'city' => 'Los Angeles',
            'state' => 'CA',
            'postal_code' => '90001',
            'country' => 'US',
        ];

        $response = $this->postJson(route('express-checkout.intent'), [
            'provider' => 'stripe',
            'shipping_address' => $address,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'amount',
            'currency',
            'clientSecret',
        ]);
        $this->assertArrayHasKey('amount', $response->json());
        $this->assertGreaterThan(0, $response->json('amount'));
    }
}
