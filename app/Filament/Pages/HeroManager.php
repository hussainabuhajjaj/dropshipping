<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\HomePageSetting;
use App\Models\StorefrontBanner;
use App\Services\Storefront\CampaignPlacementService;
use App\Services\Storefront\HeroSettings;
use App\Services\Storefront\HomeBuilderService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

class HeroManager extends BasePage
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-photo';

    protected static UnitEnum|string|null $navigationGroup = 'Storefront';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Hero Manager';

    protected static ?string $title = 'Hero Manager';

    protected static bool $adminOnly = true;

    protected string $view = 'filament.pages.hero-manager';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'locale' => 'fr',
            'hero_campaign_override' => HeroSettings::campaignOverrideEnabled(),
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Visibility')
                    ->description('Control what visitors see first on the homepage.')
                    ->schema([
                        Select::make('locale')
                            ->label('Preview locale')
                            ->options([
                                'fr' => 'French',
                                'en' => 'English',
                            ])
                            ->default('fr')
                            ->native(false)
                            ->live(),
                        Toggle::make('hero_campaign_override')
                            ->label('Show active campaign hero at top')
                            ->helperText('When enabled, an active campaign with the "home_hero" placement replaces your first hero slide. Disable to always show your Hero slides.')
                            ->live(),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save settings')
                ->icon('heroicon-o-check')
                ->action('save'),
            Action::make('edit_slides')
                ->label('Edit hero slides')
                ->icon('heroicon-o-pencil-square')
                ->url(fn (): string => \App\Filament\Resources\HomePageSettingResource::getUrl('index')),
            Action::make('open_storefront')
                ->label('Open storefront')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url('/')
                ->openUrlInNewTab(),
        ];
    }

    public function save(): void
    {
        HeroSettings::setCampaignOverrideEnabled((bool) ($this->data['hero_campaign_override'] ?? true));

        Notification::make()
            ->title('Hero settings saved')
            ->success()
            ->send();
    }

    public function getPreview(): array
    {
        $locale = $this->previewLocale();
        $override = $this->overrideEnabled();

        $campaignBanners = app(CampaignPlacementService::class)->placementBanners('home_hero', $locale);
        $campaign = $campaignBanners[0] ?? null;

        $setting = HomePageSetting::latestForLocale($locale);
        $slides = $this->slidesFrom($setting);

        $baseBanner = StorefrontBanner::query()
            ->active()
            ->byDisplayType('hero')
            ->orderBy('display_order')
            ->first();

        $merged = null;
        $mergedSource = null;

        if (is_array($campaign) && ! empty($campaign['imagePath'])) {
            $merged = [
                'badge' => $campaign['badgeText'] ?? '',
                'title' => $campaign['title'] ?? '',
                'subtitle' => $campaign['description'] ?? '',
                'image' => $campaign['imagePath'],
                'cta_label' => $campaign['ctaText'] ?? 'Explore',
                'cta_url' => $campaign['ctaUrl'] ?? '/products',
            ];
            $mergedSource = 'campaign';
        } elseif ($baseBanner && ($baseBannerImage = $this->bannerImage($baseBanner))) {
            $merged = [
                'badge' => $baseBanner->localizedValue('badge_text', $locale),
                'title' => $baseBanner->localizedValue('title', $locale),
                'subtitle' => $baseBanner->localizedValue('description', $locale),
                'image' => $baseBannerImage,
                'cta_label' => $baseBanner->localizedValue('cta_text', $locale),
                'cta_url' => $baseBanner->getCtaUrl(),
            ];
            $mergedSource = 'banner';
        }

        if ($override && $merged) {
            return $this->previewPayload($mergedSource, $merged, $campaignBanners, $slides, $baseBanner);
        }

        if ($slides !== []) {
            $first = $slides[0];
            $image = $first['image'] ?? null;

            if (is_string($image) && $image !== '' && ! str_starts_with($image, 'http')) {
                $image = app(HomeBuilderService::class)->normalizeImage($image);
            }

            return $this->previewPayload('slide', [
                'badge' => $first['kicker'] ?? ($first['badge'] ?? ''),
                'title' => $first['title'] ?? '',
                'subtitle' => $first['subtitle'] ?? '',
                'image' => $image,
                'cta_label' => $first['primary_label'] ?? ($first['primary']['label'] ?? 'Shop now'),
                'cta_url' => $first['primary_href'] ?? ($first['primary']['href'] ?? '/products'),
            ], $campaignBanners, $slides, $baseBanner);
        }

        return $this->previewPayload('seasonal', [
            'badge' => 'Seasonal preset',
            'title' => 'Seasonal fallback (code-driven)',
            'subtitle' => 'No hero slides or active campaign found. The homepage uses the built-in seasonal preset for this month.',
            'image' => null,
            'cta_label' => 'Shop now',
            'cta_url' => '/products',
        ], $campaignBanners, $slides, $baseBanner);
    }

    public function getPriorityChain(): array
    {
        $locale = $this->previewLocale();
        $override = $this->overrideEnabled();

        $campaigns = app(CampaignPlacementService::class)->placementBanners('home_hero', $locale);
        $slides = $this->slidesFrom(HomePageSetting::latestForLocale($locale));
        $baseBannerExists = StorefrontBanner::query()
            ->active()
            ->byDisplayType('hero')
            ->exists();

        return [
            [
                'label' => '1. Active campaign (home_hero)',
                'detail' => $campaigns === []
                    ? 'No active campaign uses the home_hero placement.'
                    : count($campaigns) . ' active campaign banner(s) found.',
                'status' => ! $override ? 'Disabled' : ($campaigns === [] ? 'None' : 'Found'),
                'active' => $override && $campaigns !== [],
            ],
            [
                'label' => '2. Hero slides (Home Page Settings)',
                'detail' => $slides === []
                    ? 'No hero slides configured for this locale.'
                    : count($slides) . ' slide(s) configured.',
                'status' => $slides === [] ? 'None' : 'Found',
                'active' => $slides !== [],
            ],
            [
                'label' => '3. Storefront hero banner',
                'detail' => $baseBannerExists
                    ? 'A hero banner exists and is active.'
                    : 'No active hero banner configured.',
                'status' => $baseBannerExists ? 'Found' : 'None',
                'active' => $baseBannerExists,
            ],
            [
                'label' => '4. Seasonal preset (code)',
                'detail' => 'Built-in preset selected by the current month.',
                'status' => 'Always available',
                'active' => true,
            ],
        ];
    }

    public function sourceColor(string $source): string
    {
        return match ($source) {
            'campaign' => 'warning',
            'slide' => 'success',
            'banner' => 'info',
            default => 'gray',
        };
    }

    private function previewPayload(
        string $source,
        array $slide,
        array $campaignBanners,
        array $slides,
        ?StorefrontBanner $baseBanner,
    ): array {
        return [
            'source' => $source,
            'source_label' => match ($source) {
                'campaign' => 'Active campaign',
                'slide' => 'Hero slide #1',
                'banner' => 'Storefront banner',
                default => 'Seasonal fallback',
            },
            'slide' => $slide,
            'counts' => [
                'campaigns' => count($campaignBanners),
                'slides' => count($slides),
                'base_banner' => $baseBanner !== null,
            ],
        ];
    }

    private function previewLocale(): string
    {
        return (string) ($this->data['locale'] ?? 'fr');
    }

    private function overrideEnabled(): bool
    {
        return (bool) ($this->data['hero_campaign_override'] ?? HeroSettings::campaignOverrideEnabled());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function slidesFrom(?HomePageSetting $setting): array
    {
        $slides = $setting?->hero_slides;

        if (! is_array($slides)) {
            return [];
        }

        return array_values(array_filter($slides, 'is_array'));
    }

    private function bannerImage(StorefrontBanner $banner): ?string
    {
        $homeBuilder = app(HomeBuilderService::class);

        if ($banner->image_path) {
            return $homeBuilder->normalizeImage($banner->image_path);
        }

        if ($banner->target_type === 'product' && $banner->product) {
            return $homeBuilder->normalizeImage($banner->product->images?->first()?->url);
        }

        if ($banner->target_type === 'category' && $banner->category) {
            return $homeBuilder->normalizeImage($banner->category->hero_image);
        }

        return null;
    }
}
