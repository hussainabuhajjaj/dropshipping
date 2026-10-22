<?php

namespace Tests\Feature\Api\Mobile\V1;

use App\Domain\Common\Models\Address;
use App\Domain\Orders\Models\Shipment;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_reviews_list_and_create(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        ProductReview::factory()->create([
            'product_id' => $product->id,
            'status' => 'approved',
        ]);
        ProductReview::factory()->create([
            'product_id' => $product->id,
            'status' => 'pending',
        ]);

        $list = $this->getJson('/api/mobile/v1/products/' . $product->slug . '/reviews');
        $list->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1);

        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $address = Address::factory()->create(['customer_id' => $customer->id]);
        $order = Order::create([
            'number' => 'DS-1002',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'fulfilled',
            'payment_status' => 'paid',
            'currency' => 'USD',
            'subtotal' => 50,
            'shipping_total' => 0,
            'tax_total' => 0,
            'discount_total' => 0,
            'grand_total' => 50,
            'shipping_address_id' => $address->id,
            'billing_address_id' => $address->id,
            'placed_at' => now(),
        ]);

        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'fulfillment_status' => 'fulfilled',
            'quantity' => 1,
            'unit_price' => 50,
            'total' => 50,
        ]);

        Shipment::create([
            'order_item_id' => $orderItem->id,
            'tracking_number' => 'TRK123',
            'delivered_at' => now(),
        ]);

        $create = $this->postJson('/api/mobile/v1/products/' . $product->slug . '/reviews', [
            'order_item_id' => $orderItem->id,
            'rating' => 5,
            'title' => 'Great',
            'body' => 'Loved it',
        ]);

        $create->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.rating', 5);
    }

    public function test_review_rating_validation(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);

        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/mobile/v1/products/' . $product->slug . '/reviews', [
            'order_item_id' => 999,
            'rating' => 6,
            'body' => 'Bad',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
