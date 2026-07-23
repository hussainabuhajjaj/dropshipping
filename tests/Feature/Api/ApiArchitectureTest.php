<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\ApiController;
use Tests\TestCase;

class ApiArchitectureTest extends TestCase
{
    /** @test */
    public function mobile_v1_controllers_all_extend_api_controller(): void
    {
        $controllers = glob(app_path('Http/Controllers/Api/Mobile/V1/*.php'));
        $excluded = ['ApiController.php', 'DownloadController.php'];

        foreach ($controllers as $file) {
            $basename = basename($file);
            if (in_array($basename, $excluded, true)) {
                continue;
            }

            $className = $this->classNameFromPath($file);
            $this->assertTrue(
                is_subclass_of($className, ApiController::class),
                "{$className} must extend ApiController"
            );
        }
    }

    /** @test */
    public function unified_api_controller_has_all_required_methods(): void
    {
        $ref = new \ReflectionClass(ApiController::class);

        $required = ['success', 'error', 'notFound', 'unauthorized', 'forbidden', 'validationError', 'created', 'deleted', 'noContent'];

        foreach ($required as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "ApiController must have method: {$method}"
            );
        }
    }

    /** @test */
    public function success_response_has_required_keys(): void
    {
        $controller = $this->app->make(\App\Http\Controllers\Api\Mobile\V1\HomeController::class);

        $method = new \ReflectionMethod($controller, 'success');
        $method->setAccessible(true);
        $response = $method->invoke($controller, ['test' => 'data']);

        $body = json_decode($response->getContent(), true);

        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('message', $body);
    }

    /** @test */
    public function error_response_has_required_keys(): void
    {
        $controller = $this->app->make(\App\Http\Controllers\Api\Mobile\V1\HomeController::class);

        $method = new \ReflectionMethod($controller, 'error');
        $method->setAccessible(true);
        $response = $method->invoke($controller, 'Test error', 400);

        $body = json_decode($response->getContent(), true);

        $this->assertFalse($body['success']);
        $this->assertEquals('Test error', $body['message']);
        $this->assertArrayHasKey('errors', $body);
    }

    /** @test */
    public function helper_methods_use_correct_status_codes(): void
    {
        $controller = $this->app->make(\App\Http\Controllers\Api\Mobile\V1\HomeController::class);

        $tests = [
            ['method' => 'notFound', 'args' => [], 'expectedStatus' => 404],
            ['method' => 'unauthorized', 'args' => [], 'expectedStatus' => 401],
            ['method' => 'forbidden', 'args' => [], 'expectedStatus' => 403],
            ['method' => 'validationError', 'args' => ['Invalid'], 'expectedStatus' => 422],
            ['method' => 'created', 'args' => [['id' => 1]], 'expectedStatus' => 201],
            ['method' => 'deleted', 'args' => [], 'expectedStatus' => 200],
            ['method' => 'noContent', 'args' => [], 'expectedStatus' => 204],
        ];

        foreach ($tests as $test) {
            $method = new \ReflectionMethod($controller, $test['method']);
            $method->setAccessible(true);
            $response = $method->invoke($controller, ...$test['args']);

            $this->assertEquals(
                $test['expectedStatus'],
                $response->getStatusCode(),
                "{$test['method']}() should return status {$test['expectedStatus']}"
            );
        }
    }

    /** @test */
    public function story_controller_extends_api_controller(): void
    {
        $controller = new \App\Http\Controllers\Api\Mobile\V1\StoryController();
        $this->assertInstanceOf(ApiController::class, $controller);
    }

    /** @test */
    public function payment_verification_controller_extends_api_controller(): void
    {
        $controller = $this->app->make(\App\Http\Controllers\Api\PaymentVerificationController::class);
        $this->assertInstanceOf(ApiController::class, $controller);
    }

    /** @test */
    public function legacy_storefront_controllers_are_marked_deprecated(): void
    {
        $files = glob(app_path('Http/Controllers/Api/Storefront/*.php'));

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $this->assertStringContainsString(
                '@deprecated',
                $content,
                basename($file) . ' must have @deprecated annotation'
            );
        }
    }

    private function classNameFromPath(string $path): string
    {
        $relative = str_replace([app_path(), '.php', '/'], ['App', '', '\\'], $path);

        return $relative;
    }
}
