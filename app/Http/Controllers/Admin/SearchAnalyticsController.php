<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SearchLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class SearchAnalyticsController extends Controller
{
    public function index(): Response
    {
        $analytics = $this->getSearchAnalytics();
        
        return Inertia::render('Admin/SearchAnalytics', [
            'analytics' => $analytics,
        ]);
    }
    
    public function getSearchAnalytics(): array
    {
        return [
            'today' => [
                'total_searches' => SearchLog::whereDate('created_at', today())->count(),
                'unique_queries' => SearchLog::whereDate('created_at', today())
                    ->distinct('query')
                    ->count('query'),
                'avg_execution_time' => SearchLog::whereDate('created_at', today())
                    ->avg('execution_time_ms'),
                'cache_hit_rate' => $this->getCacheHitRate('today'),
            ],
            'week' => SearchLog::getWeeklyAnalytics(),
            'popular_searches' => SearchLog::getPopularLast24Hours(20),
            'no_results_searches' => $this->getNoResultsSearches(),
            'slow_searches' => $this->getSlowSearches(),
            'trending_searches' => $this->getTrendingSearches(),
        ];
    }
    
    private function getCacheHitRate(string $period): float
    {
        $query = SearchLog::query();
        
        if ($period === 'today') {
            $query->whereDate('created_at', today());
        } elseif ($period === 'week') {
            $query->where('created_at', '>=', now()->subDays(7));
        }
        
        $total = $query->count();
        $cached = $query->where('cached', true)->count();
        
        return $total > 0 ? ($cached / $total) * 100 : 0;
    }
    
    private function getNoResultsSearches(int $limit = 10): array
    {
        return SearchLog::where('results_count', 0)
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('query')
            ->selectRaw('query, COUNT(*) as count')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->toArray();
    }
    
    private function getSlowSearches(int $limit = 10): array
    {
        return SearchLog::where('execution_time_ms', '>', 1000) // > 1 second
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('query')
            ->selectRaw('query, COUNT(*) as count, AVG(execution_time_ms) as avg_time')
            ->orderByDesc('avg_time')
            ->limit($limit)
            ->get()
            ->toArray();
    }
    
    private function getTrendingSearches(int $limit = 10): array
    {
        // Searches that are gaining popularity compared to last week
        $currentWeek = SearchLog::where('created_at', '>=', now()->subDays(7))
            ->groupBy('query')
            ->selectRaw('query, COUNT(*) as current_count')
            ->pluck('current_count', 'query');
            
        $lastWeek = SearchLog::where('created_at', '>=', now()->subDays(14))
            ->where('created_at', '<', now()->subDays(7))
            ->groupBy('query')
            ->selectRaw('query, COUNT(*) as last_count')
            ->pluck('last_count', 'query');
            
        $trending = [];
        foreach ($currentWeek as $query => $currentCount) {
            $lastCount = $lastWeek->get($query, 0);
            $growth = $lastCount > 0 ? (($currentCount - $lastCount) / $lastCount) * 100 : 0;
            
            if ($growth > 50 && $currentCount >= 3) { // At least 50% growth and 3 searches
                $trending[] = [
                    'query' => $query,
                    'current_count' => $currentCount,
                    'last_count' => $lastCount,
                    'growth_percent' => round($growth, 2),
                ];
            }
        }
        
        // Sort by growth percentage
        usort($trending, fn ($a, $b) => $b['growth_percent'] <=> $a['growth_percent']);
        
        return array_slice($trending, 0, $limit);
    }
    
    public function clearSearchCache(): JsonResponse
    {
        // Clear search-related caches
        $keys = [
            'popular_searches',
        ];
        
        foreach ($keys as $key) {
            cache()->forget($key);
        }
        
        // Clear all search suggestion caches
        $searchKeys = cache()->getRedis()?->keys('search_suggest:*') ?? [];
        foreach ($searchKeys as $key) {
            cache()->forget($key);
        }
        
        return response()->json(['message' => 'Search cache cleared successfully']);
    }
}
