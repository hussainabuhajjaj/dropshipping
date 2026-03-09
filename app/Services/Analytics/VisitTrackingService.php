<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Category;
use App\Models\Product;
use App\Models\VisitorEvent;
use App\Models\VisitorSession;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        $this->recordVisit([
            'channel' => 'website',
            'visitor_key' => $visitorKey,
            'session_id' => $request->session()?->getId(),
            'locale' => app()->getLocale(),
            'platform' => 'web',
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

        $this->recordVisit([
            'channel' => 'app',
            'visitor_key' => $visitorKey,
            'session_id' => $request->session()?->getId(),
            'locale' => app()->getLocale(),
            'platform' => trim((string) ($payload['platform'] ?? 'mobile')) ?: 'mobile',
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
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $this->truncateText($data['user_agent'] ?? null),
            'started_at' => $session->exists ? ($session->started_at ?? $now) : $now,
            'last_seen_at' => $now,
            'metadata' => $data['channel'] === 'app'
                ? array_filter([
                    'screen' => $data['metadata']['screen'] ?? null,
                ])
                : null,
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
}
