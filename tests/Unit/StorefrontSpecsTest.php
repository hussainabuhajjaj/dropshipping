<?php

namespace Tests\Unit;

use App\Support\StorefrontSpecs;
use PHPUnit\Framework\TestCase;

class StorefrontSpecsTest extends TestCase
{
    public function test_filters_supplier_payload_into_customer_facing_specs(): void
    {
        $specs = StorefrontSpecs::fromAttributes([
            'cj_pid' => '1405701002509815808',
            'cj_payload' => [
                'pid' => '1405701002509815808',
                'productName' => '["Raw supplier title"]',
                'productNameEn' => 'Wheat Straw Cutlery Set',
                'productSku' => 'CJZW1179395',
                'bigImage' => 'https://cf.cjdropshipping.com/image.png',
                'productWeight' => '200.00-320.00',
                'packingWeight' => '220.00-350.00',
                'entryNameEn' => 'Tableware',
                'materialName' => '["其他"]',
                'materialNameEnSet' => ['Others'],
                'packingNameEnSet' => ['Plastic bags'],
                'variants' => [
                    ['variantSku' => 'CJZW117939501AZ'],
                ],
            ],
            'cj_variants' => [
                ['variantSku' => 'CJZW117939501AZ'],
            ],
        ]);

        $this->assertSame([
            'Item type' => 'Tableware',
            'Material' => 'Others',
            'Packing' => 'Plastic bags',
            'Product weight' => '200-320 g',
            'Packed weight' => '220-350 g',
        ], $specs);

        $serializedSpecs = json_encode($specs, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('cj', strtolower($serializedSpecs));
        $this->assertStringNotContainsString('payload', strtolower($serializedSpecs));
        $this->assertStringNotContainsString('sku', strtolower($serializedSpecs));
        $this->assertStringNotContainsString('variant', strtolower($serializedSpecs));
        $this->assertStringNotContainsString('https://', strtolower($serializedSpecs));
        $this->assertStringNotContainsString('{', implode(' ', $specs));
    }

    public function test_does_not_render_raw_payload_when_attributes_are_the_payload(): void
    {
        $specs = StorefrontSpecs::fromAttributes([
            'pid' => '1405701002509815808',
            'productSku' => 'CJZW1179395',
            'productImage' => '["https://cf.cjdropshipping.com/image.png"]',
            'productWeight' => '200.00-320.00',
            'materialName' => '["其他"]',
            'materialNameEn' => '["Others"]',
            'packingNameEn' => '["Plastic bags"]',
            'variants' => [
                ['variantSku' => 'CJZW117939501AZ'],
            ],
        ]);

        $this->assertSame([
            'Material' => 'Others',
            'Packing' => 'Plastic bags',
            'Product weight' => '200-320 g',
        ], $specs);
    }
}
