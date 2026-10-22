<?php

namespace App\Models;

use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Enums\CampaignType;
use App\Domain\Campaigns\Models\CampaignParticipation;
use App\Domain\Campaigns\Models\CampaignWinner;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StorefrontCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'status',
        'is_active',
        'starts_at',
        'ends_at',
        'timezone',
        'locale_visibility',
        'locale_overrides',
        'priority',
        'stacking_mode',
        'exclusive_group',
        'theme',
        'placements',
        'hero_image',
        'hero_kicker',
        'hero_subtitle',
        'content',
        'promotion_ids',
        'coupon_ids',
        'banner_ids',
        'collection_ids',
        'newsletter_campaign_ids',
        'notification_config',
        'sourcing_config',
        'segment_ids',
        'lucky_draw_config',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'locale_visibility' => 'array',
        'locale_overrides' => 'array',
        'theme' => 'array',
        'placements' => 'array',
        'promotion_ids' => 'array',
        'coupon_ids' => 'array',
        'banner_ids' => 'array',
        'collection_ids' => 'array',
        'newsletter_campaign_ids' => 'array',
        'notification_config' => 'array',
        'sourcing_config' => 'array',
        'segment_ids' => 'array',
        'lucky_draw_config' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function localeOverrideMap(): array
    {
        $overrides = $this->locale_overrides ?? [];
        return collect($overrides)
            ->filter(fn ($row) => is_array($row) && ! empty($row['locale']))
            ->keyBy('locale')
            ->all();
    }

    public function localizedValue(string $field, ?string $locale): ?string
    {
        if (! $locale) {
            return $this->{$field} ?? null;
        }

        $override = $this->localeOverrideMap()[$locale] ?? null;
        if ($override && array_key_exists($field, $override) && $override[$field] !== null && $override[$field] !== '') {
            return (string) $override[$field];
        }

        return $this->{$field} ?? null;
    }

    public function isVisibleForLocale(?string $locale): bool
    {
        $allowed = $this->locale_visibility ?? [];
        if (! $allowed || ! is_array($allowed) || count($allowed) === 0) {
            return true;
        }
        if (! $locale) {
            return false;
        }
        return in_array($locale, $allowed, true);
    }

    public function resolveScheduleForLocale(?string $locale): array
    {
        $schedule = [
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'timezone' => $this->timezone,
        ];

        if (! $locale) {
            return $schedule;
        }

        $override = $this->localeOverrideMap()[$locale] ?? null;
        if (! $override || ! is_array($override)) {
            return $schedule;
        }

        return [
            'starts_at' => $override['starts_at'] ?? $schedule['starts_at'],
            'ends_at' => $override['ends_at'] ?? $schedule['ends_at'],
            'timezone' => $override['timezone'] ?? $schedule['timezone'],
        ];
    }

    public function isActiveForLocale(?string $locale, ?Carbon $now = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $allowedStatuses = ['active', 'approved', 'scheduled'];
        if (! in_array($this->status, $allowedStatuses, true)) {
            return false;
        }

        if (! $this->isVisibleForLocale($locale)) {
            return false;
        }

        $now = $now ?: now();
        $schedule = $this->resolveScheduleForLocale($locale);
        $timezone = $schedule['timezone'] ?: config('app.timezone');
        $now = $now->copy()->timezone($timezone);

        if ($schedule['starts_at']) {
            $start = $this->parseScheduleDate($schedule['starts_at'], $timezone);
            if ($start && $now->lt($start)) {
                return false;
            }
        }

        if ($schedule['ends_at']) {
            $end = $this->parseScheduleDate($schedule['ends_at'], $timezone);
            if ($end && $now->gt($end)) {
                return false;
            }
        }

        return true;
    }

    public function promotionIds(): array
    {
        return array_values(array_filter(array_map('intval', $this->promotion_ids ?? [])));
    }

    public function couponIds(): array
    {
        return array_values(array_filter(array_map('intval', $this->coupon_ids ?? [])));
    }

    public function bannerIds(): array
    {
        return array_values(array_filter(array_map('intval', $this->banner_ids ?? [])));
    }

    public function collectionIds(): array
    {
        return array_values(array_filter(array_map('intval', $this->collection_ids ?? [])));
    }

    public function newsletterCampaignIds(): array
    {
        return array_values(array_filter(array_map('intval', $this->newsletter_campaign_ids ?? [])));
    }

    public function segmentIds(): array
    {
        return array_values(array_filter(array_map('intval', $this->segment_ids ?? [])));
    }

    public function productQuery(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CampaignProductQuery::class, 'storefront_campaign_id');
    }

    public function autoCollection(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(StorefrontCollection::class, 'campaign_id');
    }

    public function notificationConfig(): array
    {
        $default = [
            'on_start' => ['push' => true, 'email' => true, 'whatsapp' => false],
            'on_ending_soon' => ['push' => true, 'email' => false, 'whatsapp' => false, 'hours_before' => 24],
            'on_end' => ['push' => false, 'email' => false, 'whatsapp' => false],
        ];

        return array_merge($default, $this->notification_config ?? []);
    }

    public function sourcingConfig(): array
    {
        $default = [
            'enabled' => false,
            'sourcing_days_before' => 7,
            'auto_create_collection' => true,
            'override_home_sections' => ['featured'],
        ];

        return array_merge($default, $this->sourcing_config ?? []);
    }

    public function isLuckyDraw(): bool
    {
        return $this->type === CampaignType::LUCKY_DRAW->value;
    }

    public function luckyDrawConfig(): array
    {
        $defaults = config('campaigns.lucky_draw.defaults', []);
        $defaults['winner_announcement_at'] = null;
        $defaults['terms'] = null;
        $defaults['faq'] = [];
        $defaults['seo'] = [];
        $defaults['landing_content'] = null;
        $defaults['cta'] = null;

        return array_merge($defaults, $this->lucky_draw_config ?? []);
    }

    /**
     * Whether a paid order should currently be considered for the lucky draw.
     * Requires: global flag on, campaign active, status active, within schedule.
     */
    public function isAcceptingLuckyDrawEntries(?Carbon $now = null): bool
    {
        if (! config('campaigns.lucky_draw.enabled', false)) {
            return false;
        }

        if (! $this->isLuckyDraw()) {
            return false;
        }

        if (! $this->is_active || $this->status !== CampaignStatus::ACTIVE->value) {
            return false;
        }

        $now = $now ?: now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && $now->gt($this->ends_at)) {
            return false;
        }

        return true;
    }

    /**
     * Front-end display payload for a lucky-draw campaign. Shared by the web
     * storefront (Inertia) and the mobile API so both channels render the
     * same eligibility, spot, prize and announcement data.
     *
     * @return array<string, mixed>|null null when the campaign is not a lucky draw.
     */
    public function luckyDrawPayload(?string $locale = null, ?int $customerId = null): ?array
    {
        if (! $this->isLuckyDraw()) {
            return null;
        }

        $config = $this->luckyDrawConfig();
        $overrides = $this->localeOverrideMap()[$locale] ?? null;

        $spotsTaken = (int) $this->participations()
            ->whereNotNull('spot_number')
            ->whereNull('deleted_at')
            ->count();
        $maxParticipants = (int) ($config['max_participants'] ?? 0);

        $payload = [
            'enabled' => (bool) config('campaigns.lucky_draw.enabled', false),
            'accepting_entries' => $this->isAcceptingLuckyDrawEntries(),
            'min_order_amount' => (float) ($config['min_order_amount'] ?? 0),
            'currency' => (string) ($config['currency'] ?? 'XOF'),
            'max_participants' => $maxParticipants,
            'spots_filled' => $spotsTaken,
            'remaining_spots' => $maxParticipants > 0 ? max(0, $maxParticipants - $spotsTaken) : 0,
            'show_remaining_spots' => (bool) ($config['show_remaining_spots'] ?? false),
            'countdown_enabled' => (bool) ($config['countdown_enabled'] ?? false),
            'grand_prize' => (string) ($config['grand_prize'] ?? 'Grand Prize'),
            'runner_up_count' => (int) ($config['runner_up_count'] ?? 0),
            'gift_card_amount' => (float) ($config['gift_card_amount'] ?? 0),
            'gift_card_currency' => (string) ($config['gift_card_currency'] ?? 'USD'),
            'guaranteed_reward_type' => $config['guaranteed_reward_type'] ?? null,
            'guaranteed_reward_value' => $config['guaranteed_reward_value'] ?? null,
            'winner_announcement_at' => $config['winner_announcement_at'] ?? null,
            'landing_content' => $this->localizedValue('landing_content', $locale) ?? $config['landing_content'] ?? null,
            'cta' => $this->localizedValue('cta', $locale) ?? $config['cta'] ?? null,
            'terms' => $this->localizedValue('terms', $locale) ?? $config['terms'] ?? null,
            'faq' => ($overrides && isset($overrides['faq'])) ? $overrides['faq'] : ($config['faq'] ?? []),
            'seo' => ($overrides && isset($overrides['seo'])) ? $overrides['seo'] : ($config['seo'] ?? []),
            'entry' => null,
        ];

        if ($customerId) {
            $entry = $this->participations()
                ->where('customer_id', $customerId)
                ->with(['winner'])
                ->first();

            if ($entry) {
                $winner = $entry->winner;

                $payload['entry'] = [
                    'spot_number' => $entry->spot_number,
                    'state' => $entry->state,
                    'qualified_at' => $entry->qualified_at?->toIso8601String(),
                    'reward_code' => $entry->reward_code,
                    'reward_issued_at' => $entry->reward_issued_at?->toIso8601String(),
                    'is_winner' => $winner !== null,
                    'prize_type' => $winner?->prize_type,
                    'prize_label' => $winner?->prize_label,
                    'prize_status' => $winner?->status,
                ];
            }
        }

        return $payload;
    }

    public function participations(): HasMany
    {
        return $this->hasMany(CampaignParticipation::class, 'campaign_id');
    }

    public function winners(): HasMany
    {
        return $this->hasMany(CampaignWinner::class, 'campaign_id');
    }

    private function parseScheduleDate($value, string $timezone): ?Carbon
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy()->timezone($timezone);
        }

        try {
            return Carbon::parse($value, $timezone);
        } catch (\Throwable) {
            return null;
        }
    }
}
