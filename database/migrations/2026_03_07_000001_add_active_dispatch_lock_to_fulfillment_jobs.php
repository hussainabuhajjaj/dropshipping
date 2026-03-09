<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'fulfillment_jobs';
    private const LOCK_COLUMN = 'active_order_item_lock';
    private const LOCK_INDEX = 'fulfillment_jobs_active_order_item_lock_unique';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        // Normalize duplicates before unique index creation: keep newest active job per order_item_id.
        $duplicateOrderItemIds = DB::table(self::TABLE)
            ->select('order_item_id')
            ->whereNotNull('order_item_id')
            ->whereNotIn('status', ['failed', 'cancelled', 'succeeded'])
            ->groupBy('order_item_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('order_item_id');

        foreach ($duplicateOrderItemIds as $orderItemId) {
            $ids = DB::table(self::TABLE)
                ->where('order_item_id', $orderItemId)
                ->whereNotIn('status', ['failed', 'cancelled', 'succeeded'])
                ->orderByDesc('id')
                ->pluck('id');

            $keepId = $ids->first();
            $dropIds = $ids->filter(static fn ($id) => $id !== $keepId)->values();

            if ($dropIds->isNotEmpty()) {
                DB::table(self::TABLE)
                    ->whereIn('id', $dropIds->all())
                    ->update([
                        'status' => 'failed',
                        'last_error' => DB::raw("COALESCE(last_error, 'Marked failed by migration: duplicate active dispatch lock')"),
                        'updated_at' => now(),
                    ]);
            }
        }

        if (! Schema::hasColumn(self::TABLE, self::LOCK_COLUMN)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->unsignedBigInteger(self::LOCK_COLUMN)
                    ->nullable()
                    ->storedAs("CASE WHEN status IN ('failed','cancelled','succeeded') THEN NULL ELSE order_item_id END")
                    ->after('order_item_id');
            });
        }

        if (! $this->indexExists(self::LOCK_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->unique(self::LOCK_COLUMN, self::LOCK_INDEX);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if ($this->indexExists(self::LOCK_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropUnique(self::LOCK_INDEX);
            });
        }

        if (Schema::hasColumn(self::TABLE, self::LOCK_COLUMN)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropColumn(self::LOCK_COLUMN);
            });
        }
    }

    private function indexExists(string $indexName): bool
    {
        return DB::table('information_schema.statistics')
            ->whereRaw('table_schema = database()')
            ->where('table_name', self::TABLE)
            ->where('index_name', $indexName)
            ->exists();
    }
};
