<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WhatsAppOrderIntent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppOrderIntentTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_create_product_whatsapp_intent(): void
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

        $response = $this->postJson('/api/whatsapp-intents', [
            'mode' => 'product',
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 2,
            'channel' => 'web',
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('totals.items_count', 2);

        $this->assertDatabaseCount('whatsapp_order_intents', 1);
        $this->assertDatabaseHas('whatsapp_order_intents', [
            'intent_type' => 'product',
            'status' => 'pending',
            'items_count' => 2,
        ]);
    }

    public function test_admin_can_convert_whatsapp_intent_into_order(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'selling_price' => 40,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 40,
            'stock_on_hand' => 5,
            'currency' => 'USD',
        ]);

        $reference = $this->postJson('/api/whatsapp-intents', [
            'mode' => 'product',
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 1,
            'channel' => 'web',
        ])->json('reference');

        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'admin')->postJson("/api/whatsapp-intents/{$reference}/convert", [
            'name' => 'WhatsApp Buyer',
            'email' => 'buyer@example.com',
            'phone' => '+22501020304',
            'line1' => 'Abidjan Street',
            'city' => 'Abidjan',
            'country' => 'CI',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'converted');

        $intent = WhatsAppOrderIntent::query()->where('reference', $reference)->firstOrFail();

        $this->assertSame('converted', $intent->status);
        $this->assertNotNull($intent->converted_order_id);
        $this->assertDatabaseHas('payments', [
            'provider' => 'whatsapp',
            'provider_reference' => $reference,
        ]);
    }
}
