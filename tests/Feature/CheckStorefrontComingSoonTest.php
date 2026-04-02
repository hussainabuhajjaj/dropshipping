<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\NewsletterSubscriber;
use App\Models\StorefrontSetting;
use Tests\TestCase;

class CheckStorefrontComingSoonTest extends TestCase
{
    public function test_storefront_returns_coming_soon_page_when_toggle_is_enabled(): void
    {
        StorefrontSetting::query()->create([
            'coming_soon_enabled' => true,
            'coming_soon_title' => 'Launching shortly',
            'coming_soon_message' => 'The storefront is being prepared.',
        ]);

        $response = $this->get('/');

        $response->assertStatus(503);
        $response->assertSee('Launching shortly');
        $response->assertSee('The storefront is being prepared.');
    }

    public function test_newsletter_signup_still_works_while_coming_soon_page_is_enabled(): void
    {
        StorefrontSetting::query()->create([
            'coming_soon_enabled' => true,
        ]);

        $response = $this->postJson('/newsletter/subscribe', [
            'email' => 'launch@example.com',
            'source' => 'coming_soon',
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'ok',
                'message' => 'Thanks for subscribing!',
            ]);

        $this->assertDatabaseHas(NewsletterSubscriber::class, [
            'email' => 'launch@example.com',
            'source' => 'coming_soon',
        ]);
    }
}
