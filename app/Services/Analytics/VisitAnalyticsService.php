<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Category;
use App\Models\Product;
use App\Models\VisitorEvent;
use App\Models\VisitorSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VisitAnalyticsService
{
    public function summary(int $activeWindowMinutes = 5, int $topLimit = 10): array
    {
        $since = now()->subMinutes($activeWindowMinutes);
        $windowStart = now()->subDay();

        return [
            'active' => [
                'total' => VisitorSession::query()->where('last_seen_at', '>=', $since)->count(),
                'website' => VisitorSession::query()->where('channel', 'website')->where('last_seen_at', '>=', $since)->count(),
                'app' => VisitorSession::query()->where('channel', 'app')->where('last_seen_at', '>=', $since)->count(),
                'signed_in' => VisitorSession::query()
                    ->where('last_seen_at', '>=', $since)
                    ->where(function ($query): void {
                        $query->whereNotNull('customer_id')->orWhereNotNull('user_id');
                    })
                    ->count(),
            ],
            'periods' => $this->periods(),
            'engagement' => $this->engagement($windowStart),
            'acquisition' => [
                'top_sources' => $this->topSources($topLimit),
                'top_landing_pages' => $this->topLandingPages($topLimit),
                'top_campaigns' => $this->topCampaigns($topLimit),
                'device_breakdown' => $this->deviceBreakdown(),
                'browser_breakdown' => $this->browserBreakdown(),
                'os_breakdown' => $this->osBreakdown(),
            ],
            'geography' => $this->geography($topLimit),
            'top_products' => $this->topEntities('product', Product::class, 'name', $topLimit),
            'top_categories' => $this->topEntities('category', Category::class, 'name', $topLimit),
            'top_pages' => $this->topPages($topLimit),
        ];
    }

    private function periods(): array
    {
        return [
            'today' => $this->periodStats(now()->startOfDay()),
            'week' => $this->periodStats(now()->startOfWeek()),
            'month' => $this->periodStats(now()->startOfMonth()),
        ];
    }

    private function periodStats(Carbon $start): array
    {
        $websiteSessions = VisitorSession::query()
            ->where('channel', 'website')
            ->where('last_seen_at', '>=', $start);

        return [
            'sessions' => (int) (clone $websiteSessions)->count(),
            'unique_visitors' => (int) (clone $websiteSessions)->distinct('visitor_key')->count('visitor_key'),
            'page_views' => (int) (clone $websiteSessions)->sum('hits_count'),
        ];
    }

    private function engagement(Carbon $windowStart): array
    {
        $websiteSessions = VisitorSession::query()
            ->where('channel', 'website')
            ->where('last_seen_at', '>=', $windowStart);

        $sessionsCount = (clone $websiteSessions)->count();
        $pageViews = (clone $websiteSessions)->sum('hits_count');
        $uniqueVisitors = (clone $websiteSessions)->distinct('visitor_key')->count('visitor_key');
        $bounces = (clone $websiteSessions)->where('hits_count', '<=', 1)->count();

        return [
            'page_views_24h' => (int) $pageViews,
            'sessions_24h' => (int) $sessionsCount,
            'unique_visitors_24h' => (int) $uniqueVisitors,
            'avg_pages_per_session_24h' => $sessionsCount > 0 ? round($pageViews / $sessionsCount, 2) : 0.0,
            'bounce_rate_24h' => $sessionsCount > 0 ? round(($bounces / $sessionsCount) * 100, 1) : 0.0,
        ];
    }

    private function topEntities(string $entityType, string $modelClass, string $nameColumn, int $limit): array
    {
        $rows = VisitorEvent::query()
            ->select('entity_id', DB::raw('MAX(entity_slug) as entity_slug'), DB::raw('COUNT(*) as views'))
            ->where('entity_type', $entityType)
            ->whereNotNull('entity_id')
            ->groupBy('entity_id')
            ->orderByDesc('views')
            ->limit($limit)
            ->get();

        $names = $modelClass::query()
            ->whereIn('id', $rows->pluck('entity_id')->filter()->all())
            ->pluck($nameColumn, 'id');

        return $rows->map(fn ($row) => [
            'id' => (int) $row->entity_id,
            'slug' => $row->entity_slug,
            'name' => $names[$row->entity_id] ?? ($row->entity_slug ?: ucfirst($entityType)),
            'views' => (int) $row->views,
        ])->all();
    }

    private function topPages(int $limit): array
    {
        return VisitorEvent::query()
            ->select(DB::raw('COALESCE(MAX(page_key), MAX(path)) as page_key'), DB::raw('MAX(path) as path'), DB::raw('COUNT(*) as views'))
            ->where('entity_type', 'page')
            ->groupByRaw('COALESCE(page_key, path)')
            ->orderByDesc('views')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'page_key' => $row->page_key,
                'path' => $row->path,
                'views' => (int) $row->views,
            ])
            ->all();
    }

    private function topSources(int $limit): array
    {
        $normalizedSources = VisitorSession::query()
            ->selectRaw('COALESCE(NULLIF(utm_source, ""), NULLIF(source_host, ""), NULLIF(source_type, ""), "direct") as source_label')
            ->selectRaw('COALESCE(NULLIF(source_type, ""), "direct") as normalized_source_type')
            ->selectRaw('NULLIF(utm_medium, "") as normalized_utm_medium')
            ->where('channel', 'website')
            ->toBase();

        return DB::query()
            ->fromSub($normalizedSources, 'sources')
            ->select(
                'source_label',
                DB::raw('MAX(normalized_source_type) as source_type'),
                DB::raw('MAX(normalized_utm_medium) as utm_medium'),
                DB::raw('COUNT(*) as sessions')
            )
            ->groupBy('source_label')
            ->orderByDesc('sessions')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'source' => (string) $row->source_label,
                'source_type' => $row->source_type,
                'utm_medium' => $row->utm_medium,
                'sessions' => (int) $row->sessions,
            ])
            ->all();
    }

    private function topLandingPages(int $limit): array
    {
        return VisitorSession::query()
            ->select(
                DB::raw('COALESCE(MAX(landing_page_key), MAX(landing_path)) as page_key'),
                DB::raw('MAX(landing_path) as path'),
                DB::raw('COUNT(*) as sessions')
            )
            ->where('channel', 'website')
            ->where(function ($query): void {
                $query->whereNotNull('landing_page_key')->orWhereNotNull('landing_path');
            })
            ->groupByRaw('COALESCE(landing_page_key, landing_path)')
            ->orderByDesc('sessions')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'page_key' => $row->page_key,
                'path' => $row->path,
                'sessions' => (int) $row->sessions,
            ])
            ->all();
    }

    private function topCampaigns(int $limit): array
    {
        return VisitorSession::query()
            ->select('utm_campaign', DB::raw('MAX(utm_source) as utm_source'), DB::raw('MAX(utm_medium) as utm_medium'), DB::raw('COUNT(*) as sessions'))
            ->where('channel', 'website')
            ->whereNotNull('utm_campaign')
            ->groupBy('utm_campaign')
            ->orderByDesc('sessions')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'campaign' => $row->utm_campaign,
                'utm_source' => $row->utm_source,
                'utm_medium' => $row->utm_medium,
                'sessions' => (int) $row->sessions,
            ])
            ->all();
    }

    private function deviceBreakdown(): array
    {
        return VisitorSession::query()
            ->select(DB::raw('COALESCE(device_type, "unknown") as device_type'), DB::raw('COUNT(*) as sessions'))
            ->where('channel', 'website')
            ->groupByRaw('COALESCE(device_type, "unknown")')
            ->orderByDesc('sessions')
            ->get()
            ->map(fn ($row) => [
                'device_type' => $row->device_type,
                'sessions' => (int) $row->sessions,
            ])
            ->all();
    }

    private function browserBreakdown(): array
    {
        return VisitorSession::query()
            ->select(DB::raw('COALESCE(browser_family, "unknown") as browser_family'), DB::raw('COUNT(*) as sessions'))
            ->where('channel', 'website')
            ->groupByRaw('COALESCE(browser_family, "unknown")')
            ->orderByDesc('sessions')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'browser_family' => $row->browser_family,
                'sessions' => (int) $row->sessions,
            ])
            ->all();
    }

    private function osBreakdown(): array
    {
        return VisitorSession::query()
            ->select(DB::raw('COALESCE(os_family, "unknown") as os_family'), DB::raw('COUNT(*) as sessions'))
            ->where('channel', 'website')
            ->groupByRaw('COALESCE(os_family, "unknown")')
            ->orderByDesc('sessions')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'os_family' => $row->os_family,
                'sessions' => (int) $row->sessions,
            ])
            ->all();
    }

    private function geography(int $limit): array
    {
        if (! Schema::hasColumn('visitor_sessions', 'country_code')) {
            return [
                'coverage' => [
                    'countries' => 0,
                    'cities' => 0,
                ],
                'top_countries' => [],
                'top_cities' => [],
                'map_points' => [],
            ];
        }

        $base = VisitorSession::query()->where('channel', 'website');

        $countries = (clone $base)
            ->whereNotNull('country_code')
            ->distinct('country_code')
            ->count('country_code');

        $cities = (clone $base)
            ->whereNotNull('city_name')
            ->distinct(DB::raw('CONCAT(COALESCE(city_name, ""), "|", COALESCE(country_code, ""))'))
            ->count(DB::raw('CONCAT(COALESCE(city_name, ""), "|", COALESCE(country_code, ""))'));

        $topCountries = (clone $base)
            ->selectRaw('COALESCE(country_name, country_code) as country')
            ->selectRaw('COALESCE(country_code, "--") as country_code')
            ->selectRaw('COUNT(*) as sessions')
            ->whereNotNull('country_code')
            ->groupByRaw('COALESCE(country_name, country_code), COALESCE(country_code, "--")')
            ->orderByDesc('sessions')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'country' => (string) $row->country,
                'country_code' => (string) $row->country_code,
                'sessions' => (int) $row->sessions,
            ])
            ->all();

        $topCities = (clone $base)
            ->selectRaw('COALESCE(city_name, "Unknown") as city')
            ->selectRaw('COALESCE(country_name, country_code, "--") as country')
            ->selectRaw('COUNT(*) as sessions')
            ->whereNotNull('city_name')
            ->groupByRaw('COALESCE(city_name, "Unknown"), COALESCE(country_name, country_code, "--")')
            ->orderByDesc('sessions')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'city' => (string) $row->city,
                'country' => (string) $row->country,
                'sessions' => (int) $row->sessions,
            ])
            ->all();

        $mapPoints = (clone $base)
            ->selectRaw('COALESCE(city_name, country_name, country_code, "Unknown") as label')
            ->selectRaw('COALESCE(country_name, country_code, "--") as country')
            ->selectRaw('AVG(latitude) as latitude')
            ->selectRaw('AVG(longitude) as longitude')
            ->selectRaw('COUNT(*) as sessions')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->groupByRaw('COALESCE(city_name, country_name, country_code, "Unknown"), COALESCE(country_name, country_code, "--")')
            ->orderByDesc('sessions')
            ->limit(24)
            ->get()
            ->map(fn ($row) => [
                'label' => (string) $row->label,
                'country' => (string) $row->country,
                'latitude' => (float) $row->latitude,
                'longitude' => (float) $row->longitude,
                'sessions' => (int) $row->sessions,
            ])
            ->all();

        return [
            'coverage' => [
                'countries' => (int) $countries,
                'cities' => (int) $cities,
            ],
            'top_countries' => $topCountries,
            'top_cities' => $topCities,
            'map_points' => $mapPoints,
        ];
    }
}
