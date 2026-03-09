<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Category;
use App\Models\Product;
use App\Models\VisitorEvent;
use App\Models\VisitorSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class VisitAnalyticsService
{
    public function summary(int $activeWindowMinutes = 5, int $topLimit = 10): array
    {
        $since = now()->subMinutes($activeWindowMinutes);

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
            'top_products' => $this->topEntities('product', Product::class, 'name', $topLimit),
            'top_categories' => $this->topEntities('category', Category::class, 'name', $topLimit),
            'top_pages' => $this->topPages($topLimit),
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
}
