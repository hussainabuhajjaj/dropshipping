<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

trait LogsUserActivity
{
    /**
     * Log user activity with context
     */
    protected function logActivity(
        string $action, 
        array $context = [], 
        string $level = 'info',
        ?string $modelType = null,
        ?int $modelId = null
    ): void {
        $user = Auth::user();
        
        $logData = [
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'action' => $action,
            'context' => $context,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
            'timestamp' => now()->toIso8601String(),
        ];

        // Log to Laravel log file
        Log::{$level}($action, $logData);

        // Store in database
        try {
            \App\Models\UserActivityLog::create([
                'user_id' => $user?->id,
                'action' => $action,
                'description' => $this->formatActivityDescription($action, $context),
                'model_type' => $modelType,
                'model_id' => $modelId,
                'properties' => $context,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'url' => request()->fullUrl(),
            ]);
        } catch (\Exception $e) {
            // Silently fail if table doesn't exist or other DB error
            Log::warning('Failed to log user activity to database', [
                'error' => $e->getMessage(),
                'action' => $action,
            ]);
        }
    }

    /**
     * Format activity description for human readability
     */
    protected function formatActivityDescription(string $action, array $context): string
    {
        $descriptions = [
            'cj.catalog.viewed' => 'Viewed CJ Catalog',
            'cj.catalog.filtered' => 'Applied filters to CJ Catalog',
            'cj.catalog.product.imported' => 'Imported product from CJ Catalog',
            'cj.catalog.bulk.imported' => sprintf('Bulk imported %d products', $context['count'] ?? 0),
            'cj.catalog.pricing.previewed' => sprintf('Previewed pricing for %d products', $context['count'] ?? 0),
            'cj.catalog.inventory.checked' => sprintf('Checked inventory for %d products', $context['count'] ?? 0),
            'cj.catalog.exported' => sprintf('Exported %d products to CSV', $context['count'] ?? 0),
            'cj.product.activated' => sprintf('Activated product: %s', $context['product_name'] ?? 'Unknown'),
            'cj.product.deactivated' => sprintf('Deactivated product: %s', $context['product_name'] ?? 'Unknown'),
        ];

        return $descriptions[$action] ?? $action;
    }

    /**
     * Log import activity
     */
    protected function logImportActivity(array $pids, array $options, array $result): void
    {
        $this->logActivity('cj.catalog.bulk.imported', [
            'count' => count($pids),
            'pids' => array_slice($pids, 0, 10), // Log first 10 PIDs
            'total_pids' => count($pids),
            'margin' => $options['margin'] ?? null,
            'enrich' => $options['enrich'] ?? null,
            'auto_activate' => $options['auto_activate'] ?? null,
            'skip_existing' => $options['skip_existing'] ?? null,
            'batch_size' => $options['batch_size'] ?? null,
            'result' => [
                'imported' => $result['imported'] ?? 0,
                'activated' => $result['activated'] ?? 0,
                'failed' => $result['failed_activation'] ?? 0,
                'translations_queued' => $result['translations_queued'] ?? 0,
                'seo_queued' => $result['seo_queued'] ?? 0,
            ],
        ]);
    }

    /**
     * Log filter activity
     */
    protected function logFilterActivity(array $filters): void
    {
        $activeFilters = array_filter($filters, fn($value) => !empty($value));
        
        if (!empty($activeFilters)) {
            $this->logActivity('cj.catalog.filtered', [
                'filters' => $activeFilters,
                'filter_count' => count($activeFilters),
            ]);
        }
    }

    /**
     * Log pricing preview activity
     */
    protected function logPricingPreview(array $pids, float $margin): void
    {
        $this->logActivity('cj.catalog.pricing.previewed', [
            'count' => count($pids),
            'margin' => $margin,
            'pids_sample' => array_slice($pids, 0, 5),
        ]);
    }

    /**
     * Log inventory check activity
     */
    protected function logInventoryCheck(array $pids, array $results): void
    {
        $this->logActivity('cj.catalog.inventory.checked', [
            'count' => count($pids),
            'in_stock' => $results['in_stock'] ?? 0,
            'low_stock' => $results['low_stock'] ?? 0,
            'out_of_stock' => $results['out_of_stock'] ?? 0,
        ]);
    }

    /**
     * Log export activity
     */
    protected function logExportActivity(int $count, string $filename): void
    {
        $this->logActivity('cj.catalog.exported', [
            'count' => $count,
            'filename' => $filename,
            'format' => 'csv',
        ]);
    }

    /**
     * Log product activation
     */
    protected function logProductActivation(int $productId, string $productName, bool $activated): void
    {
        $action = $activated ? 'cj.product.activated' : 'cj.product.deactivated';
        
        $this->logActivity($action, [
            'product_id' => $productId,
            'product_name' => $productName,
            'activated' => $activated,
        ]);
    }
}
