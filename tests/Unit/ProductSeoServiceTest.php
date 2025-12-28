<?php

namespace Tests\Unit;

use App\Domain\Products\Models\Product;
use App\Models\ProductTranslation;
use App\Services\AI\DeepSeekClient;
use App\Services\AI\ModerationService;
use App\Services\AI\ProductSeoService;
use App\Services\AI\ProductTranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ProductSeoServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_parses_noisy_json_and_updates_product()
    {
        $product = Product::create([
            'name' => 'Test product',
            'description' => 'A short description',
        ]);

        $noisy = "Here is the computed result:\n{\n  \"meta_title\": \"Test title\",\n  \"meta_description\": \"Test description\"\n}\n\nExtra commentary.";

        $deep = Mockery::mock(DeepSeekClient::class);
        $deep->shouldReceive('chat')->once()->andReturn($noisy);

        $translationService = Mockery::mock(ProductTranslationService::class);
        $translationService->shouldReceive('translate')->never();

        $moderation = Mockery::mock(ModerationService::class);
        $moderation->shouldReceive('isAllowed')->once()->andReturn(true);

        $service = new ProductSeoService($deep, $translationService, $moderation);

        $service->generate($product, 'en', true);

        $product->refresh();

        $this->assertSame('Test title', $product->meta_title);
        $this->assertSame('Test description', $product->meta_description);
        $this->assertArrayHasKey('en', $product->seo_metadata ?? []);
        $this->assertSame('deepseek', $product->seo_metadata['en']['provider']);
    }

    public function test_generates_seo_for_non_english_locale_using_translations()
    {
        $product = Product::create([
            'name' => 'Original Name',
            'description' => 'Original Description',
        ]);

        $deep = Mockery::mock(DeepSeekClient::class);

        // translate should be called at least twice (name and description)
        $deep->shouldReceive('translate')->with('Original Name', 'en', 'fr')->andReturn('Nom FR');
        $deep->shouldReceive('translate')->with('Original Description', 'en', 'fr')->andReturn('Description FR');

        // chat should return a simple JSON
        $deep->shouldReceive('chat')->once()->andReturn('{"meta_title":"Titre FR","meta_description":"Description SEO FR"}');

        $translationService = new ProductTranslationService($deep);

        $moderation = Mockery::mock(ModerationService::class);
        $moderation->shouldReceive('isAllowed')->once()->andReturn(true);

        $service = new ProductSeoService($deep, $translationService, $moderation);

        $service->generate($product, 'fr', true);

        $product->refresh();

        $this->assertSame('Titre FR', $product->meta_title);
        $this->assertSame('Description SEO FR', $product->meta_description);

        // Also assert translation was created
        $translation = ProductTranslation::where('product_id', $product->id)->where('locale', 'fr')->first();
        $this->assertNotNull($translation);
        $this->assertSame('Nom FR', $translation->name);
        $this->assertSame('Description FR', $translation->description);

        $this->assertArrayHasKey('fr', $product->seo_metadata ?? []);
        $this->assertSame('deepseek', $product->seo_metadata['fr']['provider']);
    }

    public function test_moderation_blocks_flagged_seo()
    {
        $product = Product::create([
            'name' => 'Name',
            'description' => 'Desc',
        ]);

        $deep = Mockery::mock(DeepSeekClient::class);
        $deep->shouldReceive('chat')->once()->andReturn('{"meta_title":"Good", "meta_description":"Contains bannedword here"}');

        $translationService = Mockery::mock(ProductTranslationService::class);
        $translationService->shouldReceive('translate')->never();

        $moderation = Mockery::mock(ModerationService::class);
        $moderation->shouldReceive('isAllowed')->once()->andReturn(false);

        $service = new ProductSeoService($deep, $translationService, $moderation);

        $service->generate($product, 'en', true);

        $product->refresh();

        $this->assertNull($product->meta_title);
        $this->assertNull($product->meta_description);
        $this->assertNull($product->seo_metadata);
    }
}

