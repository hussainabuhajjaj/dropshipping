<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Contracts\Cart\CartManagerContract;
use App\Contracts\User\PreferenceContract;
use App\Services\Cart\CartIdentityService;
use App\Services\User\UserPreferenceService;
use Tests\TestCase;

class ContractComplianceTest extends TestCase
{
    /** @test */
    public function cart_identity_service_implements_cart_manager_contract(): void
    {
        $service = $this->app->make(CartIdentityService::class);
        $this->assertInstanceOf(CartManagerContract::class, $service);
    }

    /** @test */
    public function user_preference_service_implements_preference_contract(): void
    {
        $service = $this->app->make(UserPreferenceService::class);
        $this->assertInstanceOf(PreferenceContract::class, $service);
    }

    /** @test */
    public function cart_manager_contract_is_bound(): void
    {
        $resolved = $this->app->make(CartManagerContract::class);
        $this->assertInstanceOf(CartIdentityService::class, $resolved);
    }

    /** @test */
    public function preference_contract_is_bound(): void
    {
        $resolved = $this->app->make(PreferenceContract::class);
        $this->assertInstanceOf(UserPreferenceService::class, $resolved);
    }

    /** @test */
    public function cart_manager_contract_defines_all_required_methods(): void
    {
        $ref = new \ReflectionClass(CartManagerContract::class);

        $methods = ['resolveCart', 'mergeGuestCartIntoCustomer', 'guestTokenForRequest', 'resolveVisitorId'];

        foreach ($methods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "CartManagerContract must define method: {$method}"
            );
        }
    }

    /** @test */
    public function preference_contract_defines_all_required_methods(): void
    {
        $ref = new \ReflectionClass(PreferenceContract::class);

        $methods = ['getPreferences', 'setCurrency', 'setLanguage', 'getAvailableCurrencies', 'getAvailableLanguages'];

        foreach ($methods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "PreferenceContract must define method: {$method}"
            );
        }
    }

    /** @test */
    public function product_catalog_contract_is_defined(): void
    {
        $this->assertTrue(
            interface_exists(\App\Contracts\Catalog\ProductCatalogContract::class),
            'ProductCatalogContract must exist'
        );
    }

    /** @test */
    public function order_repository_contract_is_defined(): void
    {
        $this->assertTrue(
            interface_exists(\App\Contracts\Orders\OrderRepositoryContract::class),
            'OrderRepositoryContract must exist'
        );
    }

    /** @test */
    public function checkout_contract_is_defined(): void
    {
        $this->assertTrue(
            interface_exists(\App\Contracts\Checkout\CheckoutContract::class),
            'CheckoutContract must exist'
        );
    }

    /** @test */
    public function payment_processor_contract_is_defined(): void
    {
        $this->assertTrue(
            interface_exists(\App\Contracts\Payments\PaymentProcessorContract::class),
            'PaymentProcessorContract must exist'
        );
    }
}
