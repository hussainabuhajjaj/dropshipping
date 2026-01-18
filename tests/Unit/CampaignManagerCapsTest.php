<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use App\Services\CampaignManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignManagerCapsTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_order_discount_is_capped(): void
    {
        config(['promotions.caps.first_order_max_discount' => 5.00]);

        $customer = Customer::factory()->create();

        $manager = app(CampaignManager::class);
        $result = $manager->bestForCart([], 200.0, $customer);

        $this->assertSame(5.0, $result['amount']);
    }

    public function test_shipping_support_is_not_beaten_by_first_order(): void
    {
        config(['promotions.caps.first_order_max_discount' => 20.00]);

        $customer = Customer::factory()->create();

        $promo = Promotion::create([
            'name' => 'Logistics Support',
            'type' => 'auto_discount',
            'value_type' => 'percentage',
            'value' => 5,
            'stacking_rule' => 'combinable',
            'promotion_intent' => 'shipping_support',
            'is_active' => true,
        ]);

        PromotionTarget::create([
            'promotion_id' => $promo->id,
            'target_type' => 'product',
            'target_id' => 1,
        ]);

        $manager = app(CampaignManager::class);
        $result = $manager->bestForCart([
            ['product_id' => 1, 'category_id' => 1],
        ], 100.0, $customer);

        $this->assertSame('promotion', $result['source']);
    }
}
