<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\HomePageSetting;
use App\Models\StorefrontCampaign;
use App\Models\User;
use App\Services\Storefront\HeroSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HeroCampaignOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_hero_override_defaults_to_enabled(): void
    {
        $this->assertTrue(HeroSettings::campaignOverrideEnabled());
    }

    public function test_campaign_hero_override_can_be_disabled(): void
    {
        HeroSettings::setCampaignOverrideEnabled(false);

        $this->assertFalse(HeroSettings::campaignOverrideEnabled());
    }

    public function test_campaign_hero_is_shown_first_when_override_is_enabled(): void
    {
        $this->createHomePageSetting('CMS Slide');
        $campaign = $this->createCampaign();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('homeContent.hero_slides.0.id', 'campaign-hero-campaign-' . $campaign->id)
                ->where('homeContent.hero_slides.0.title', 'Campaign Hero')
            );
    }

    public function test_hero_slide_is_shown_when_override_is_disabled(): void
    {
        HeroSettings::setCampaignOverrideEnabled(false);
        $this->createHomePageSetting('CMS Slide');
        $this->createCampaign();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('homeContent.hero_slides.0.title', 'CMS Slide')
            );
    }

    public function test_hero_manager_page_renders_for_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get('/admin/hero-manager')
            ->assertOk()
            ->assertSee('Live preview')
            ->assertSee('Priority chain');
    }

    private function createHomePageSetting(string $title): HomePageSetting    {
        return HomePageSetting::query()->create([
            'locale' => 'fr',
            'hero_slides' => [
                [
                    'kicker' => 'CMS',
                    'title' => $title,
                    'subtitle' => 'CMS subtitle',
                    'image' => 'https://example.com/slide.jpg',
                    'primary_label' => 'Shop now',
                    'primary_href' => '/products',
                ],
            ],
        ]);
    }

    private function createCampaign(): StorefrontCampaign
    {
        return StorefrontCampaign::query()->create([
            'name' => 'Campaign Hero',
            'slug' => 'campaign-hero',
            'type' => 'seasonal',
            'status' => 'active',
            'is_active' => true,
            'placements' => ['home_hero'],
            'hero_kicker' => 'Campaign',
            'hero_subtitle' => 'Campaign subtitle',
            'hero_image' => 'https://example.com/campaign.jpg',
        ]);
    }
}
