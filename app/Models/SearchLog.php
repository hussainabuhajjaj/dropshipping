<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'query',
        'type',
        'ip_address',
        'user_agent',
        'user_id',
        'results_count',
        'execution_time_ms',
        'cached',
    ];

    protected $casts = [
        'execution_time_ms' => 'float',
        'cached' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get popular searches in the last 24 hours
     */
    public static function getPopularLast24Hours(int $limit = 10): array
    {
        return self::where('created_at', '>=', now()->subHours(24))
            ->where('query', '!=', '')
            ->whereRaw('LENGTH(query) >= 2')
            ->groupBy('query')
            ->selectRaw('query, COUNT(*) as count, AVG(results_count) as avg_results')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get search analytics for the last 7 days
     */
    public static function getWeeklyAnalytics(): array
    {
        return [
            'total_searches' => self::where('created_at', '>=', now()->subDays(7))->count(),
            'unique_queries' => self::where('created_at', '>=', now()->subDays(7))
                ->distinct('query')
                ->count('query'),
            'avg_execution_time' => self::where('created_at', '>=', now()->subDays(7))
                ->avg('execution_time_ms'),
            'cache_hit_rate' => self::where('created_at', '>=', now()->subDays(7))
                ->where('cached', true)
                ->count() / max(1, self::where('created_at', '>=', now()->subDays(7))->count()) * 100,
        ];
    }
}
