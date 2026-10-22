<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Analytics\VisitTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GuestCartFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_add_to_cart_and_checkout_redirects_to_login(): void
    {
        $visitorId = 'gst_test_web_001';
        [$product, $variant] = $this->createPurchasableProduct();

        $this->withCookie(VisitTrackingService::WEBSITE_COOKIE, $visitorId)
            ->post('/cart', [
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'quantity' => 2,
            ])
            ->assertRedirect();

        $cart = Cart::query()->where('visitor_id', $visitorId)->first();

        $this->assertNotNull($cart);
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart?->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        $this->withCookie(VisitTrackingService::WEBSITE_COOKIE, $visitorId)
            ->get('/cart')
            ->assertOk();

        $this->withCookie(VisitTrackingService::WEBSITE_COOKIE, $visitorId)
            ->get('/checkout')
            ->assertRedirect(route('login'));
    }

    public function test_guest_cart_merges_into_customer_cart_after_login(): void
    {
        $visitorId = 'gst_test_web_merge';
        [$product, $variant] = $this->createPurchasableProduct();

        $customer = Customer::query()->create([
            'first_name' => 'Merge',
            'last_name' => 'Tester',
            'email' => 'merge@example.com',
            'password' => Hash::make('password'),
            'address_line1' => '',
        ]);

        $customerCart = Cart::query()->create(['user_id' => $customer->id]);
        $customerItem = $customerCart->items()->create([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'fulfillment_provider_id' => $product->default_fulfillment_provider_id,
            'quantity' => 1,
            'stock_on_hand' => $variant->stock_on_hand,
        ]);
        $customerItem->forceFill(['updated_at' => now()->subMinutes(10)])->save();

        $guestCart = Cart::query()->create([
            'session_id' => 'guest-session',
            'visitor_id' => $visitorId,
        ]);
        $guestItem = $guestCart->items()->create([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'fulfillment_provider_id' => $product->default_fulfillment_provider_id,
            'quantity' => 4,
            'stock_on_hand' => $variant->stock_on_hand,
        ]);
        $guestItem->forceFill(['updated_at' => now()])->save();

        $this->withCookie(VisitTrackingService::WEBSITE_COOKIE, $visitorId)
            ->post('/login', [
                'email' => $customer->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('account.index', absolute: false));

        $customerCart->refresh();

        $this->assertDatabaseMissing('carts', ['id' => $guestCart->id]);
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $customerCart->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 4,
        ]);
    }

    public function test_mobile_guest_cart_returns_guest_token_and_supports_whatsapp_intent(): void
    {
        [$product, $variant] = $this->createPurchasableProduct();

        $cartResponse = $this->postJson('/api/mobile/v1/cart/items', [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $cartResponse->assertOk();

        $guestToken = (string) $cartResponse->json('data.guest_token');

        $this->assertNotSame('', $guestToken);

        $this->getJson('/api/mobile/v1/cart', [
            'X-Guest-Token' => $guestToken,
        ])->assertOk()
            ->assertJsonPath('data.guest_token', $guestToken);

        $this->postJson('/api/mobile/v1/whatsapp-intents', [
            'mode' => 'cart',
            'channel' => 'mobile',
            'guest_token' => $guestToken,
        ])->assertCreated()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('totals.items_count', 1);
    }

    /**
     * @return array{0: Product, 1: ProductVariant}
     */
    private function createPurchasableProduct(): array
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'selling_price' => 25,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 25,
            'stock_on_hand' => 10,
            'currency' => 'USD',
        ]);

        return [$product, $variant];
    }
}
