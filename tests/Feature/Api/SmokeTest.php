<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\TestCase;

/**
 * Structural smoke tests — verify the code is wired correctly without needing a DB.
 * For integration tests, run the manual checklist below.
 */
class SmokeTest extends TestCase
{
    // ─── Controller Structure ────────────────────────────────────────────

    public function test_all_mobile_v1_controllers_extend_api_controller(): void
    {
        $excluded = ['ApiController', 'DownloadController'];
        foreach (glob(app_path('Http/Controllers/Api/Mobile/V1/*.php')) as $file) {
            $class = $this->classNameFromFile($file);
            if (in_array(class_basename($class), $excluded, true)) {
                continue;
            }
            $this->assertTrue(
                is_subclass_of($class, \App\Http\Controllers\Api\ApiController::class),
                "{$class} must extend ApiController"
            );
        }
    }

    // ─── Response Envelope ──────────────────────────────────────────────

    public function test_success_response_has_correct_envelope(): void
    {
        $c = $this->app->make(\App\Http\Controllers\Api\Mobile\V1\ApiController::class);
        $m = new \ReflectionMethod($c, 'success');
        $m->setAccessible(true);

        $r = $m->invoke($c, ['foo' => 'bar'], 'Done');
        $body = json_decode($r->getContent(), true);

        $this->assertTrue($body['success']);
        $this->assertEquals('Done', $body['message']);
        $this->assertEquals(['foo' => 'bar'], $body['data']);
        $this->assertNull($body['errors'] ?? null);
    }

    public function test_error_response_has_correct_envelope(): void
    {
        $c = $this->app->make(\App\Http\Controllers\Api\Mobile\V1\ApiController::class);
        $m = new \ReflectionMethod($c, 'error');
        $m->setAccessible(true);

        $r = $m->invoke($c, 'Bad request', 400, ['field' => 'invalid']);
        $body = json_decode($r->getContent(), true);

        $this->assertFalse($body['success']);
        $this->assertEquals('Bad request', $body['message']);
        $this->assertEquals(['field' => 'invalid'], $body['errors']);
    }

    // ─── Helper Status Codes ────────────────────────────────────────────

    public function test_all_helper_methods_return_correct_status(): void
    {
        $c = $this->app->make(\App\Http\Controllers\Api\Mobile\V1\ApiController::class);

        $cases = [
            ['notFound', [], 404],
            ['unauthorized', [], 401],
            ['forbidden', [], 403],
            ['validationError', ['Invalid data'], 422],
            ['created', [['id' => 1]], 201],
            ['deleted', [], 200],
            ['noContent', [], 204],
        ];

        foreach ($cases as [$method, $args, $expectedStatus]) {
            $m = new \ReflectionMethod($c, $method);
            $m->setAccessible(true);
            $r = $m->invoke($c, ...$args);
            $this->assertEquals($expectedStatus, $r->status(),
                "{$method}() should return {$expectedStatus}");
        }
    }

    // ─── Contracts Are Bound ────────────────────────────────────────────

    public function test_cart_manager_contract_resolves(): void
    {
        $service = $this->app->make(\App\Contracts\Cart\CartManagerContract::class);
        $this->assertInstanceOf(\App\Services\Cart\CartIdentityService::class, $service);
    }

    public function test_preference_contract_resolves(): void
    {
        $service = $this->app->make(\App\Contracts\User\PreferenceContract::class);
        $this->assertInstanceOf(\App\Services\User\UserPreferenceService::class, $service);
    }

    public function test_product_catalog_contract_resolves(): void
    {
        $repo = $this->app->make(\App\Contracts\Catalog\ProductCatalogContract::class);
        $this->assertInstanceOf(\App\Repositories\Api\ProductRepository::class, $repo);
    }

    public function test_order_repository_contract_resolves(): void
    {
        $repo = $this->app->make(\App\Contracts\Orders\OrderRepositoryContract::class);
        $this->assertInstanceOf(\App\Repositories\Api\OrderRepository::class, $repo);
    }

    // ─── Session Decoupling ─────────────────────────────────────────────

    public function test_cart_controller_has_no_session_writes(): void
    {
        $code = file_get_contents(app_path('Http/Controllers/Api/Mobile/V1/CartController.php'));
        $this->assertStringNotContainsString("session(['cart_coupon'", $code);
        $this->assertStringNotContainsString("session()->forget('cart_coupon')", $code);
    }

    public function test_checkout_controller_has_no_session_writes(): void
    {
        $code = file_get_contents(app_path('Http/Controllers/Api/Mobile/V1/CheckoutController.php'));
        $this->assertStringNotContainsString("session()->forget('cart_coupon')", $code);
    }

    public function test_payment_controller_has_no_session_reference(): void
    {
        $code = file_get_contents(app_path('Http/Controllers/Api/Mobile/V1/PaymentController.php'));
        $this->assertStringNotContainsString("session(['reference'", $code);
    }

    public function test_cart_model_dual_mode_coupon_fallback(): void
    {
        $code = file_get_contents(app_path('Models/Cart.php'));
        $this->assertStringContainsString('applied_coupon_data ?? session', $code,
            'Cart::getSummery() must read DB first, session as fallback');
    }

    public function test_cart_model_has_coupon_fillable_and_casts(): void
    {
        $cart = new \App\Models\Cart();
        $this->assertContains('applied_coupon_code', $cart->getFillable());
        $this->assertContains('applied_coupon_data', $cart->getFillable());
        $this->assertArrayHasKey('applied_coupon_data', $cart->getCasts());
        $this->assertEquals('array', $cart->getCasts()['applied_coupon_data']);
    }

    // ─── Database Config ────────────────────────────────────────────────

    public function test_mysql_connection_has_read_write_split(): void
    {
        $config = config('database.connections.mysql');
        $this->assertArrayHasKey('read', $config, 'Read config must exist');
        $this->assertArrayHasKey('write', $config, 'Write config must exist');
        $this->assertArrayHasKey('sticky', $config, 'Sticky config must exist');
        $this->assertTrue($config['sticky'], 'Sticky must default to true');
    }

    // ─── Helper ─────────────────────────────────────────────────────────

    private function classNameFromFile(string $path): string
    {
        return str_replace([app_path(), '.php', '/'], ['App', '', '\\'], $path);
    }
}
