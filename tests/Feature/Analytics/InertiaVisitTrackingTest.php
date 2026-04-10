<?php

namespace Tests\Feature\Analytics;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InertiaVisitTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_inertia_navigation_is_tracked_when_analytics_consent_is_granted(): void
    {
        $this->withCookie('storefront_cookie_consent', json_encode(['analytics' => true], JSON_THROW_ON_ERROR));

        // Inertia client navigation: XHR + JSON + X-Inertia.
        $response = $this
            ->withHeader('X-Inertia', 'true')
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->withHeader('Accept', 'application/json')
            ->get('/');

        $response->assertOk();

        $this->assertDatabaseCount('visitor_sessions', 1);
        $this->assertDatabaseHas('visitor_sessions', [
            'channel' => 'website',
            'hits_count' => 1,
        ]);

        $this->assertDatabaseCount('visitor_events', 1);
        $this->assertDatabaseHas('visitor_events', [
            'event_type' => 'page_view',
        ]);
    }
}

