<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\TestCase;

class SessionDecouplingTest extends TestCase
{
    /** @test */
    public function api_cart_controller_does_not_use_session_for_coupons(): void
    {
        $content = file_get_contents(app_path('Http/Controllers/Api/Mobile/V1/CartController.php'));

        // Should NOT have session write calls for coupons
        $this->assertStringNotContainsString(
            "session(['cart_coupon'",
            $content,
            'CartController must not write coupons to session'
        );

        $this->assertStringNotContainsString(
            "session()->forget('cart_coupon')",
            $content,
            'CartController must not forget coupon from session'
        );
    }

    /** @test */
    public function api_checkout_controller_does_not_write_coupons_to_session(): void
    {
        $content = file_get_contents(app_path('Http/Controllers/Api/Mobile/V1/CheckoutController.php'));

        $this->assertStringNotContainsString(
            "session()->forget('cart_coupon')",
            $content,
            'CheckoutController must not forget coupon from session'
        );

        $this->assertStringNotContainsString(
            "session(['cart_coupon'",
            $content,
            'CheckoutController must not write coupon to session'
        );
    }

    /** @test */
    public function api_payment_controller_does_not_store_reference_in_session(): void
    {
        $content = file_get_contents(app_path('Http/Controllers/Api/Mobile/V1/PaymentController.php'));

        $this->assertStringNotContainsString(
            "session(['reference'",
            $content,
            'PaymentController must not store reference in session'
        );

        $this->assertStringNotContainsString(
            "session('reference'",
            $content,
            'PaymentController must not read reference from session'
        );
    }

    /** @test */
    public function cart_model_falls_back_to_session_for_get_summery(): void
    {
        $content = file_get_contents(app_path('Models/Cart.php'));

        // Should read from DB first, session as fallback (dual-mode transition)
        $this->assertStringContainsString(
            'applied_coupon_data ?? session',
            $content,
            'Cart::getSummery() must read applied_coupon_data first, session as fallback'
        );
    }

    /** @test */
    public function checkout_controller_reads_coupon_from_cart_column(): void
    {
        $content = file_get_contents(app_path('Http/Controllers/Api/Mobile/V1/CheckoutController.php'));

        // Should read from cart DB field
        $this->assertStringContainsString(
            'applied_coupon_data',
            $content,
            'CheckoutController must read coupon from cart.applied_coupon_data'
        );
    }

    /** @test */
    public function cart_model_has_coupon_columns_in_fillable(): void
    {
        $fillable = (new \App\Models\Cart())->getFillable();

        $this->assertContains('applied_coupon_code', $fillable);
        $this->assertContains('applied_coupon_data', $fillable);
    }

    /** @test */
    public function cart_model_casts_coupon_data_to_array(): void
    {
        $casts = (new \App\Models\Cart())->getCasts();

        $this->assertArrayHasKey('applied_coupon_data', $casts);
        $this->assertEquals('array', $casts['applied_coupon_data']);
    }
}
