<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class CheckoutSmokeTest extends TestCase
{
    public function test_home_page_loads(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    public function test_product_index_loads(): void
    {
        $response = $this->get('/products');
        $response->assertStatus(200);
    }

    public function test_cart_page_loads(): void
    {
        $response = $this->get('/cart');
        $response->assertStatus(200);
    }

    public function test_checkout_page_requires_cart(): void
    {
        $response = $this->get('/checkout');
        $response->assertStatus(302);
    }
}
