<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Services;

use App\Domain\WooCommerce\Models\WooCommerceSyncLog;
use Illuminate\Support\Facades\Log;

class WooCommerceLogService
{
    public function success(string $entityType, ?int $entityId, string $action, array $meta = []): void
    {
        $this->record($entityType, $entityId, $action, 'success', null, null, $meta);
    }

    public function info(string $entityType, ?int $entityId, string $action, array $meta = []): void
    {
        $this->record($entityType, $entityId, $action, 'success', null, null, $meta);
    }

    public function error(string $entityType, ?int $entityId, string $action, string $error, array $meta = []): void
    {
        $this->record($entityType, $entityId, $action, 'failed', $error, null, $meta);
    }

    public function warning(string $entityType, ?int $entityId, string $action, string $error, array $meta = []): void
    {
        $this->record($entityType, $entityId, $action, 'warning', $error, null, $meta);
    }

    public function skipped(string $entityType, ?int $entityId, string $action, string $reason): void
    {
        $this->record($entityType, $entityId, $action, 'skipped', null, $reason);
    }

    private function record(
        string $entityType,
        ?int $entityId,
        string $action,
        string $status = 'success',
        ?string $error = null,
        ?string $requestSummary = null,
        ?array $meta = null,
    ): void {
        try {
            WooCommerceSyncLog::create([
                'type' => 'woocommerce',
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'action' => $action,
                'status' => $status,
                'error' => $error,
                'request_summary' => $requestSummary,
                'meta' => $meta,
                'completed_at' => now(),
            ]);

            Log::channel('woocommerce')->info("WC Sync: {$status}|{$entityType}#{$entityId}|{$action}", [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'action' => $action,
                'status' => $status,
                'error' => $error,
            ]);
        } catch (\Throwable $e) {
            Log::channel('woocommerce')->warning('Failed to write WooCommerce sync log', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function recent(int $limit = 50): iterable
    {
        return WooCommerceSyncLog::query()
            ->where('type', 'woocommerce')
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function failures(string $entityType = '', int $limit = 50): iterable
    {
        $query = WooCommerceSyncLog::query()
            ->where('type', 'woocommerce')
            ->where('status', 'failed');

        if ($entityType !== '') {
            $query->where('entity_type', $entityType);
        }

        return $query->latest()->limit($limit)->get();
    }
}
