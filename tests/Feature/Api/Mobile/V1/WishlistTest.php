<?php

namespace Tests\Feature\Api\Mobile\V1;

use App\Models\Customer;
use App\Models\Product;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_wishlist_add_remove_and_list(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);

        Sanctum::actingAs($customer);

        $add = $this->postJson('/api/mobile/v1/wishlist/' . $product->id);
        $add->assertStatus(201)->assertJsonPath('success', true);

        $this->assertDatabaseHas('wishlist_items', [
            'customer_id' => $customer->id,
            'product_id' => $product->id,
        ]);

        $addAgain = $this->postJson('/api/mobile/v1/wishlist/' . $product->id);
        $addAgain->assertStatus(201)->assertJsonPath('success', true);

        $this->assertSame(1, WishlistItem::query()
            ->where('customer_id', $customer->id)
            ->where('product_id', $product->id)
            ->count());

        $list = $this->getJson('/api/mobile/v1/wishlist');
        $list->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');

        $remove = $this->deleteJson('/api/mobile/v1/wishlist/' . $product->id);
        $remove->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseMissing('wishlist_items', [
            'customer_id' => $customer->id,
            'product_id' => $product->id,
        ]);
    }
}
