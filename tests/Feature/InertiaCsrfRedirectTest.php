<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class InertiaCsrfRedirectTest extends TestCase
{
    public function test_csrf_failure_on_inertia_request_redirects_to_safe_page_not_api(): void
    {
        $response = $this->put('/api/user-preferences/language', ['language' => 'fr'], [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertStatus(409);

        $location = (string) $response->headers->get('X-Inertia-Location');
        $this->assertStringNotContainsString('/api/', $location);
        $this->assertStringNotContainsString('user-preferences', $location);
    }

    public function test_csrf_failure_redirects_to_referring_page_when_safe(): void
    {
        $response = $this->put('/api/user-preferences/language', ['language' => 'fr'], [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
            'Referer' => 'https://dropshipping.test/products',
        ]);

        $response->assertStatus(409);

        $location = (string) $response->headers->get('X-Inertia-Location');
        $this->assertSame('https://dropshipping.test/products', $location);
    }

    public function test_valid_csrf_still_updates_language(): void
    {
        Session::start();
        $token = csrf_token();

        $response = $this->put('/api/user-preferences/language', ['language' => 'fr'], [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-CSRF-TOKEN' => $token,
            'Referer' => 'https://dropshipping.test/products',
        ]);

        $response->assertStatus(409);
        $this->assertSame('https://dropshipping.test/products', $response->headers->get('X-Inertia-Location'));
    }
}
