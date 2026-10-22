<?php

namespace Tests\Feature\Api\Mobile\V1;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_list_returns_paginated_meta(): void
    {
        $category = Category::factory()->create();
        Product::factory()->count(3)->create([
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/mobile/v1/products?per_page=2');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['currentPage', 'lastPage', 'perPage', 'total'],
            ]);
    }

    public function test_products_list_returns_mobile_merchandising_fields(): void
    {
        $category = Category::factory()->create([
            'name' => 'Summer',
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'name' => 'Linen Shirt',
            'description' => '<p>Breathable everyday staple for warm weather.</p>',
            'selling_price' => 80,
            'shipping_estimate_days' => 6,
            'marketing_metadata' => [
                'en' => [
                    'title' => 'Summer Pick',
                    'description' => 'A lighter, sharper take on your daily shirt.',
                ],
            ],
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 80,
            'compare_at_price' => 100,
            'stock_on_hand' => 3,
        ]);

        $response = $this->getJson('/api/mobile/v1/products');

        $response->assertOk()
            ->assertJsonPath('data.0.primary_image', null)
            ->assertJsonPath('data.0.subtitle', 'Summer Pick')
            ->assertJsonPath('data.0.short_description', 'A lighter, sharper take on your daily shirt.')
            ->assertJsonPath('data.0.stock_on_hand', 3)
            ->assertJsonPath('data.0.inventory_status', 'low_stock')
            ->assertJsonPath('data.0.inventory_label', 'Low stock')
            ->assertJsonPath('data.0.is_low_stock', true)
            ->assertJsonPath('data.0.has_discount', true)
            ->assertJsonPath('data.0.discount_percent', 20)
            ->assertJsonPath('data.0.savings_amount', 20)
            ->assertJsonPath('data.0.delivery.lead_time_days', 6)
            ->assertJsonPath('data.0.delivery.label', 'Ships in 6 days');
    }

    public function test_product_detail_marks_wishlist_items_for_authenticated_customer(): void
    {
        $category = Category::factory()->create();
        $customer = Customer::query()->create([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'wishlist@example.com',
            'password' => Hash::make('password'),
            'address_line1' => '123 Test Street',
        ]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        WishlistItem::factory()->create([
            'customer_id' => $customer->id,
            'product_id' => $product->id,
        ]);

        Sanctum::actingAs($customer);

        $response = $this->getJson("/api/mobile/v1/products/{$product->slug}");

        $response->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.is_in_wishlist', true);
    }
}
