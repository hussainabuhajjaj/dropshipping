<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Services;

use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Services\Api\ApiException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CJWarehouseService
{
    private const CACHE_KEY = 'cj.warehouses.list';
    private const CACHE_TTL = 60 * 60 * 24; // 24 hours

    public function __construct(private readonly CJDropshippingClient $client)
    {
    }

    /**
     * Get warehouse options as key-value pairs (ID => Name)
     * Returns cached result if available, refreshes from API otherwise
     */
    public function getWarehouseOptions(bool $refresh = false): array
    {
        if (!$refresh) {
            $cached = Cache::get(self::CACHE_KEY);
            if (is_array($cached) && !empty($cached)) {
                return $cached;
            }
        }

        try {
            $response = $this->client->listGlobalWarehouses();
            $warehouses = $response->data ?? [];

            if (!is_array($warehouses)) {
                Log::warning('CJ listGlobalWarehouses returned non-array data');
                return $this->getDefaultWarehouses();
            }

            $options = [];
            foreach ($warehouses as $warehouse) {
                $id = $warehouse['id'] ?? $warehouse['warehouseId'] ?? null;
                $name = $warehouse['name'] ?? $warehouse['warehouseName'] ?? null;

                if ($id && $name) {
                    $options[$id] = $name;
                }
            }

            if (empty($options)) {
                Log::warning('CJ listGlobalWarehouses returned empty warehouse list');
                return $this->getDefaultWarehouses();
            }

            // Cache the result
            Cache::put(self::CACHE_KEY, $options, self::CACHE_TTL);

            return $options;
        } catch (ApiException $e) {
            Log::warning('CJ listGlobalWarehouses API failed', [
                'error' => $e->getMessage(),
                'status' => $e->status,
            ]);
            return $this->getDefaultWarehouses();
        } catch (\Throwable $e) {
            Log::error('CJ listGlobalWarehouses unexpected error', [
                'error' => $e->getMessage(),
            ]);
            return $this->getDefaultWarehouses();
        }
    }

    /**
     * Clear warehouse cache (e.g., after updating settings)
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Log::info('CJ warehouse cache cleared');
    }

    /**
     * Fallback default warehouses if API fails
     */
    private function getDefaultWarehouses(): array
    {
        return [
            'CN' => 'China Warehouse',
            'US' => 'USA Warehouse',
            'DE' => 'Germany Warehouse',
            'UK' => 'UK Warehouse',
            'AU' => 'Australia Warehouse',
        ];
    }
}
