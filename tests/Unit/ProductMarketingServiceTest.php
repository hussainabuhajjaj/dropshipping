<?php

namespace Tests\Unit;

use App\Domain\Products\Models\Product;
use App\Services\AI\DeepSeekClient;
use App\Services\AI\ModerationService;
use App\Services\AI\ProductMarketingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ProductMarketingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_marketing_and_saves_metadata()
    {
        $product = Product::create([
            'name' => 'Test product',
            'description' => 'A short description',
        ]);

        $deep = Mockery::mock(DeepSeekClient::class);
        $deep->shouldReceive('chat')->once()->andReturn('{"title":"Great product","description":"This is an amazing product you will love."}');

        $moderation = Mockery::mock(ModerationService::class);
        $moderation->shouldReceive('isAllowed')->once()->andReturn(true);

        $service = new ProductMarketingService($deep, $moderation);

        $service->generate($product, 'en', true);

        $product->refresh();

        $this->assertArrayHasKey('en', $product->marketing_metadata ?? []);
        $this->assertSame('Great product', $product->marketing_metadata['en']['title']);
    }

    public function test_moderation_blocks_marketing()
    {
        $product = Product::create([
            'name' => 'Test product',
            'description' => 'A short description',
        ]);

        $deep = Mockery::mock(DeepSeekClient::class);
        $deep->shouldReceive('chat')->once()->andReturn('{"title":"Bad","description":"bad content"}');

        $moderation = Mockery::mock(ModerationService::class);
        $moderation->shouldReceive('isAllowed')->once()->andReturn(false);

        $service = new ProductMarketingService($deep, $moderation);

        $service->generate($product, 'en', true);

        $product->refresh();

        $this->assertNull($product->marketing_metadata);
    }
}
