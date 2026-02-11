<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\StorefrontBanner;
use App\Models\StorefrontCampaign;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class CampaignPlacementService
{
    public function __construct(private readonly HomeBuilderService $homeBuilder)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function placementBanners(string $placement, string $locale, int $limit = 0): array
    {
        $campaigns = $this->activeCampaigns($locale)
            ->filter(function (StorefrontCampaign $campaign) use ($placement) {
                $placements = $campaign->placements ?? [];
                return is_array($placements) && in_array($placement, $placements, true);
            })
            ->values();

        $banners = [];
        $exclusiveGroups = [];

        foreach ($campaigns as $campaign) {
            if ($campaign->stacking_mode === 'exclusive') {
                $group = $campaign->exclusive_group;
                if ($group && in_array($group, $exclusiveGroups, true)) {
                    continue;
                }
                if ($group) {
                    $exclusiveGroups[] = $group;
                }
            }

            $campaignBanners = $this->campaignBanners($campaign, $locale);
            if (empty($campaignBanners)) {
                $campaignBanners = [$this->mapCampaignBanner($campaign, $locale)];
            }

            foreach ($campaignBanners as $banner) {
                $banners[] = $banner;
                if ($limit > 0 && count($banners) >= $limit) {
                    return array_slice($banners, 0, $limit);
                }
            }

            if ($campaign->stacking_mode === 'exclusive' && ! $campaign->exclusive_group) {
                break;
            }
        }

        return $limit > 0 ? array_slice($banners, 0, $limit) : $banners;
    }

    /** @return Collection<int, StorefrontCampaign> */
    private function activeCampaigns(string $locale): Collection
    {
        return StorefrontCampaign::query()
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->orderByDesc('starts_at')
            ->get()
            ->filter(fn (StorefrontCampaign $campaign) => $campaign->isActiveForLocale($locale))
            ->values();
    }

    /** @return array<int, array<string, mixed>> */
    private function campaignBanners(StorefrontCampaign $campaign, ?string $locale = null): array
    {
        $bannerIds = $campaign->bannerIds();
        if (empty($bannerIds)) {
            return [];
        }

        return StorefrontBanner::query()
            ->whereIn('id', $bannerIds)
            ->orderBy('display_order')
            ->get()
            ->map(fn (StorefrontBanner $banner) => $this->mapBanner($banner, $locale))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function mapBanner(StorefrontBanner $banner, ?string $locale = null): array
    {
        $targeting = is_array($banner->targeting ?? null) ? $banner->targeting : [];

        return [
            'id' => $banner->id,
            'title' => $banner->localizedValue('title', $locale),
            'description' => $banner->localizedValue('description', $locale),
            'type' => $banner->type,
            'displayType' => $banner->display_type,
            'imagePath' => $this->homeBuilder->normalizeImage($banner->image_path),
            'backgroundColor' => $banner->background_color,
            'textColor' => $banner->text_color,
            'badgeText' => $banner->localizedValue('badge_text', $locale),
            'badgeColor' => $banner->badge_color,
            'ctaText' => $banner->localizedValue('cta_text', $locale),
            'ctaUrl' => $banner->getCtaUrl(),
            'imageMode' => $targeting['image_mode'] ?? 'split',
        ];
    }

    /** @return array<string, mixed> */
    private function mapCampaignBanner(StorefrontCampaign $campaign, ?string $locale = null): array
    {
        return [
            'id' => 'campaign-' . $campaign->id,
            'title' => $campaign->localizedValue('name', $locale) ?? $campaign->name,
            'description' => $campaign->localizedValue('hero_subtitle', $locale) ?? $campaign->hero_subtitle,
            'type' => 'campaign',
            'displayType' => 'hero',
            'imagePath' => $this->homeBuilder->normalizeImage($campaign->hero_image),
            'backgroundColor' => Arr::get($campaign->theme, 'primary', '#0f172a'),
            'textColor' => '#ffffff',
            'badgeText' => $campaign->localizedValue('hero_kicker', $locale) ?? $campaign->hero_kicker,
            'badgeColor' => Arr::get($campaign->theme, 'accent', '#f59e0b'),
            'ctaText' => $locale === 'fr' ? 'Explorer' : 'Explore',
            'ctaUrl' => '/campaigns/' . $campaign->slug,
            'imageMode' => Arr::get($campaign->theme, 'image_mode', 'cover'),
        ];
    }
}
