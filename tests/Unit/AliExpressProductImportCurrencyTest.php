<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Fulfillment\Models\FulfillmentProvider;
use App\Domain\Products\Services\AliExpressProductImportService;
use App\Infrastructure\Fulfillment\Clients\AliExpressClient;
use ReflectionMethod;
use Tests\TestCase;

class AliExpressProductImportCurrencyTest extends TestCase
{
    public function test_1688_payload_resolves_to_cny_when_currency_is_missing(): void
    {
        $service = new AliExpressProductImportService(
            new AliExpressClient(new FulfillmentProvider())
        );

        $method = new ReflectionMethod($service, 'resolveCurrency');
        $method->setAccessible(true);

        $currency = $method->invoke($service, [
            'source_url' => 'https://detail.1688.com/offer/952095514123.html',
            'ae_item_sku_info_dtos' => [
                ['offer_sale_price' => '43.00'],
            ],
        ]);

        $this->assertSame('CNY', $currency);
    }

    public function test_currency_aliases_are_normalized(): void
    {
        $service = new AliExpressProductImportService(
            new AliExpressClient(new FulfillmentProvider())
        );

        $method = new ReflectionMethod($service, 'resolveCurrency');
        $method->setAccessible(true);

        $currency = $method->invoke($service, [
            'ae_item_sku_info_dtos' => [
                ['currency_code' => 'RMB', 'offer_sale_price' => '43.00'],
            ],
        ]);

        $this->assertSame('CNY', $currency);
    }
}
