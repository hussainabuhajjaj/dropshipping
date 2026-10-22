<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class UserPreferenceControllerTest extends TestCase
{
    public function test_language_toggle_reloads_referring_page(): void
    {
        $response = $this->put('/api/user-preferences/language', ['language' => 'fr'], [
            'X-Inertia' => 'true',
            'Referer' => 'https://dropshipping.test/products',
        ]);

        $response->assertStatus(409);
        $this->assertSame('https://dropshipping.test/products', $response->headers->get('X-Inertia-Location'));
    }

    public function test_language_toggle_falls_back_to_home_without_referer(): void
    {
        $response = $this->put('/api/user-preferences/language', ['language' => 'fr'], [
            'X-Inertia' => 'true',
        ]);

        $response->assertStatus(409);
        $this->assertStringNotContainsString('/api/', (string) $response->headers->get('X-Inertia-Location'));
    }

    public function test_currency_toggle_returns_location(): void
    {
        $response = $this->put('/api/user-preferences/currency', ['currency' => 'XOF'], [
            'X-Inertia' => 'true',
            'Referer' => 'https://dropshipping.test/',
        ]);

        $response->assertStatus(409);
        $this->assertSame('https://dropshipping.test/', $response->headers->get('X-Inertia-Location'));
    }

    public function test_plain_update_returns_json(): void
    {
        $response = $this->put('/api/user-preferences', ['language' => 'fr'], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
    }
}
