<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Category;
use App\Models\Product;
use App\Models\VisitorEvent;
use App\Models\VisitorSession;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class VisitTrackingService
{
    public const WEBSITE_COOKIE = 'storefront_visitor_id';

    public function generateVisitorKey(): string
    {
        return (string) Str::uuid();
    }

    public function trackWebsiteRequest(Request $request, string $visitorKey): void
    {
        $route = $request->route();
        if (! $route) {
            return;
        }

        $descriptor = $this->describeWebsiteRequest($request);
        $acquisition = $this->resolveAcquisition($request);
        $device = $this->resolveDeviceContext($request->userAgent());

        $this->recordVisit([
            'channel' => 'website',
            'visitor_key' => $visitorKey,
            'session_id' => $request->session()?->getId(),
            'locale' => app()->getLocale(),
            'platform' => 'web',
            'source_type' => $acquisition['source_type'],
            'source_host' => $acquisition['source_host'],
            'utm_source' => $acquisition['utm_source'],
            'utm_medium' => $acquisition['utm_medium'],
            'utm_campaign' => $acquisition['utm_campaign'],
            'utm_term' => $acquisition['utm_term'],
            'utm_content' => $acquisition['utm_content'],
            'device_type' => $device['device_type'],
            'browser_family' => $device['browser_family'],
            'os_family' => $device['os_family'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'customer' => auth('customer')->user(),
            'user' => auth()->user(),
            'event_type' => $descriptor['event_type'],
            'route_name' => $route->getName(),
            'path' => '/' . ltrim($request->path(), '/'),
            'page_key' => $descriptor['page_key'],
            'entity_type' => $descriptor['entity_type'],
            'entity_id' => $descriptor['entity_id'],
            'entity_slug' => $descriptor['entity_slug'],
            'referrer' => $request->headers->get('referer'),
            'referrer_host' => $this->extractHost($request->headers->get('referer')),
            'metadata' => [
                'full_url' => $request->fullUrl(),
            ],
        ]);
    }

    public function trackMobileVisit(array $payload, Request $request): void
    {
        $visitorKey = trim((string) ($payload['visitor_key'] ?? ''));
        if ($visitorKey === '') {
            return;
        }

        $screen = trim((string) ($payload['screen'] ?? 'screen'));
        $path = trim((string) ($payload['path'] ?? $screen));
        $entityType = $this->normalizeEntityType($payload['entity_type'] ?? null);
        $entityId = isset($payload['entity_id']) && is_numeric($payload['entity_id']) ? (int) $payload['entity_id'] : null;
        $entitySlug = isset($payload['entity_slug']) ? trim((string) $payload['entity_slug']) : null;
        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
        $device = $this->resolveDeviceContext($request->userAgent());

        $this->recordVisit([
            'channel' => 'app',
            'visitor_key' => $visitorKey,
            'session_id' => $request->session()?->getId(),
            'locale' => app()->getLocale(),
            'platform' => trim((string) ($payload['platform'] ?? 'mobile')) ?: 'mobile',
            'source_type' => 'app',
            'source_host' => null,
            'utm_source' => null,
            'utm_medium' => null,
            'utm_campaign' => null,
            'utm_term' => null,
            'utm_content' => null,
            'device_type' => $device['device_type'],
            'browser_family' => $device['browser_family'],
            'os_family' => $device['os_family'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'customer' => auth('sanctum')->user(),
            'user' => null,
            'event_type' => $entityType ? $entityType . '_view' : 'screen_view',
            'route_name' => 'mobile.' . $screen,
            'path' => $path !== '' ? $path : '/' . $screen,
            'page_key' => 'app:' . ($path !== '' ? $path : $screen),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_slug' => $entitySlug ?: null,
            'referrer' => null,
            'referrer_host' => null,
            'metadata' => array_merge($metadata, [
                'screen' => $screen,
            ]),
        ]);
    }

    private function recordVisit(array $data): void
    {
        $now = now();
        $session = VisitorSession::query()->firstOrNew([
            'channel' => $data['channel'],
            'visitor_key' => $data['visitor_key'],
        ]);

        $session->fill([
            'customer_id' => $this->resolveAuthId($data['customer'] ?? null),
            'user_id' => $this->resolveAuthId($data['user'] ?? null),
            'session_id' => $data['session_id'] ?? null,
            'locale' => $data['locale'] ?? null,
            'platform' => $data['platform'] ?? null,
            'source_type' => $session->exists ? ($session->source_type ?: ($data['source_type'] ?? null)) : ($data['source_type'] ?? null),
            'source_host' => $session->exists ? ($session->source_host ?: ($data['source_host'] ?? null)) : ($data['source_host'] ?? null),
            'utm_source' => $session->exists ? ($session->utm_source ?: ($data['utm_source'] ?? null)) : ($data['utm_source'] ?? null),
            'utm_medium' => $session->exists ? ($session->utm_medium ?: ($data['utm_medium'] ?? null)) : ($data['utm_medium'] ?? null),
            'utm_campaign' => $session->exists ? ($session->utm_campaign ?: ($data['utm_campaign'] ?? null)) : ($data['utm_campaign'] ?? null),
            'utm_term' => $session->exists ? ($session->utm_term ?: ($data['utm_term'] ?? null)) : ($data['utm_term'] ?? null),
            'utm_content' => $session->exists ? ($session->utm_content ?: ($data['utm_content'] ?? null)) : ($data['utm_content'] ?? null),
            'device_type' => $session->exists ? ($session->device_type ?: ($data['device_type'] ?? null)) : ($data['device_type'] ?? null),
            'browser_family' => $session->exists ? ($session->browser_family ?: ($data['browser_family'] ?? null)) : ($data['browser_family'] ?? null),
            'os_family' => $session->exists ? ($session->os_family ?: ($data['os_family'] ?? null)) : ($data['os_family'] ?? null),
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $this->truncateText($data['user_agent'] ?? null),
            'landing_route_name' => $session->exists ? ($session->landing_route_name ?: ($data['route_name'] ?? null)) : ($data['route_name'] ?? null),
            'landing_path' => $session->exists ? ($session->landing_path ?: ($data['path'] ?? null)) : ($data['path'] ?? null),
            'landing_page_key' => $session->exists ? ($session->landing_page_key ?: ($data['page_key'] ?? null)) : ($data['page_key'] ?? null),
            'last_route_name' => $data['route_name'] ?? null,
            'last_path' => $data['path'] ?? null,
            'last_page_key' => $data['page_key'] ?? null,
            'hits_count' => max(0, (int) ($session->hits_count ?? 0)) + 1,
            'started_at' => $session->exists ? ($session->started_at ?? $now) : $now,
            'last_seen_at' => $now,
            'metadata' => $this->buildSessionMetadata($session->metadata, $data),
        ]);
        $session->save();

        $pageKey = (string) ($data['page_key'] ?? $data['path']);
        $dedupeKey = implode(':', [
            'visitor-event',
            $data['channel'],
            $data['visitor_key'],
            md5($pageKey),
        ]);

        if (! Cache::add($dedupeKey, 1, now()->addSeconds(15))) {
            return;
        }

        VisitorEvent::query()->create([
            'visitor_session_id' => $session->id,
            'event_type' => $data['event_type'],
            'route_name' => $data['route_name'] ?? null,
            'path' => (string) $data['path'],
            'page_key' => $pageKey,
            'entity_type' => $data['entity_type'] ?? null,
            'entity_id' => $data['entity_id'] ?? null,
            'entity_slug' => $data['entity_slug'] ?? null,
            'referrer' => $this->truncateText($data['referrer'] ?? null, 500),
            'referrer_host' => $data['referrer_host'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'occurred_at' => $now,
        ]);
    }

    private function describeWebsiteRequest(Request $request): array
    {
        $route = $request->route();
        $routeName = (string) ($route?->getName() ?? '');
        $path = '/' . ltrim($request->path(), '/');

        $entityType = 'page';
        $entityId = null;
        $entitySlug = null;

        $product = $route?->parameter('product');
        $category = $route?->parameter('category');

        if ($product instanceof Product) {
            $entityType = 'product';
            $entityId = $product->getKey();
            $entitySlug = $product->slug;
        } elseif ($category instanceof Category) {
            $entityType = 'category';
            $entityId = $category->getKey();
            $entitySlug = $category->slug;
        } elseif ($routeName === 'home') {
            $entityType = 'page';
            $entitySlug = 'home';
        } elseif ($routeName !== '') {
            $entityType = 'page';
            $entitySlug = $routeName;
        }

        return [
            'event_type' => $entityType . '_view',
            'page_key' => $routeName !== '' ? $routeName : $path,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_slug' => $entitySlug,
        ];
    }

    private function resolveAuthId(mixed $user): ?int
    {
        if ($user instanceof Authenticatable && is_numeric($user->getAuthIdentifier())) {
            return (int) $user->getAuthIdentifier();
        }

        return null;
    }

    private function normalizeEntityType(mixed $value): ?string
    {
        $type = trim((string) $value);
        if ($type === '') {
            return null;
        }

        return match ($type) {
            'product', 'category', 'page', 'collection', 'campaign', 'search', 'cart', 'checkout', 'screen' => $type,
            default => 'page',
        };
    }

    private function truncateText(mixed $value, int $limit = 1000): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        return Str::limit($text, $limit, '');
    }

    private function resolveAcquisition(Request $request): array
    {
        $utmSource = $this->truncateText($request->query('utm_source'), 100);
        $utmMedium = $this->truncateText($request->query('utm_medium'), 100);
        $utmCampaign = $this->truncateText($request->query('utm_campaign'), 150);
        $utmTerm = $this->truncateText($request->query('utm_term'), 150);
        $utmContent = $this->truncateText($request->query('utm_content'), 150);
        $referrerHost = $this->extractHost($request->headers->get('referer'));
        $requestHost = $this->extractHost($request->getSchemeAndHttpHost());

        $sourceType = 'direct';
        if ($utmSource || $utmMedium || $utmCampaign) {
            $sourceType = 'campaign';
        } elseif ($referrerHost && $referrerHost !== $requestHost) {
            $sourceType = 'referral';
        } elseif ($referrerHost) {
            $sourceType = 'internal';
        }

        return [
            'source_type' => $sourceType,
            'source_host' => $referrerHost,
            'utm_source' => $utmSource,
            'utm_medium' => $utmMedium,
            'utm_campaign' => $utmCampaign,
            'utm_term' => $utmTerm,
            'utm_content' => $utmContent,
        ];
    }

    private function resolveDeviceContext(?string $userAgent): array
    {
        $userAgent = strtolower(trim((string) $userAgent));
        if ($userAgent === '') {
            return [
                'device_type' => null,
                'browser_family' => null,
                'os_family' => null,
            ];
        }

        $deviceType = match (true) {
            str_contains($userAgent, 'ipad'), str_contains($userAgent, 'tablet') => 'tablet',
            str_contains($userAgent, 'mobile'), str_contains($userAgent, 'iphone'), str_contains($userAgent, 'android') => 'mobile',
            default => 'desktop',
        };

        $browserFamily = match (true) {
            str_contains($userAgent, 'edg/') => 'Edge',
            str_contains($userAgent, 'opr/'), str_contains($userAgent, 'opera') => 'Opera',
            str_contains($userAgent, 'chrome/') && ! str_contains($userAgent, 'edg/') => 'Chrome',
            str_contains($userAgent, 'safari/') && ! str_contains($userAgent, 'chrome/') => 'Safari',
            str_contains($userAgent, 'firefox/') => 'Firefox',
            default => 'Other',
        };

        $osFamily = match (true) {
            str_contains($userAgent, 'iphone'), str_contains($userAgent, 'ipad'), str_contains($userAgent, 'ios') => 'iOS',
            str_contains($userAgent, 'android') => 'Android',
            str_contains($userAgent, 'windows') => 'Windows',
            str_contains($userAgent, 'mac os'), str_contains($userAgent, 'macintosh') => 'macOS',
            str_contains($userAgent, 'linux') => 'Linux',
            default => 'Other',
        };

        return [
            'device_type' => $deviceType,
            'browser_family' => $browserFamily,
            'os_family' => $osFamily,
        ];
    }

    private function extractHost(mixed $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }

    private function buildSessionMetadata(mixed $existing, array $data): ?array
    {
        $existing = is_array($existing) ? $existing : [];
        $metadata = array_filter([
            'screen' => $data['metadata']['screen'] ?? $existing['screen'] ?? null,
            'landing_full_url' => $existing['landing_full_url'] ?? ($data['metadata']['full_url'] ?? null),
            'last_full_url' => $data['metadata']['full_url'] ?? $existing['last_full_url'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');

        return $metadata !== [] ? $metadata : null;
    }
}
