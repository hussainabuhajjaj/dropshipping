<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('storefront_collection_products')) {
            Schema::create('storefront_collection_products', function (Blueprint $table) {
                $table->id();
                $table->foreignId('storefront_collection_id')->constrained('storefront_collections')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();

                $table->unique(['storefront_collection_id', 'product_id'], 'scp_collection_product_unique');
                $table->index(['storefront_collection_id', 'position'], 'scp_collection_position_index');
            });
        }

        if (Schema::hasColumn('storefront_collections', 'manual_products')) {
            $collections = DB::table('storefront_collections')
                ->select(['id', 'manual_products'])
                ->whereNotNull('manual_products')
                ->get();

            foreach ($collections as $collection) {
                $raw = $collection->manual_products;
                if (! is_string($raw) || trim($raw) === '') {
                    continue;
                }

                $decoded = json_decode($raw, true);
                if (! is_array($decoded)) {
                    continue;
                }

                foreach (array_values($decoded) as $index => $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $productId = $row['product_id'] ?? null;
                    if (! $productId) {
                        continue;
                    }

                    $position = $row['position'] ?? $index;

                    DB::table('storefront_collection_products')->updateOrInsert(
                        [
                            'storefront_collection_id' => $collection->id,
                            'product_id' => (int) $productId,
                        ],
                        [
                            'position' => (int) $position,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }

            Schema::table('storefront_collections', function (Blueprint $table) {
                $table->dropColumn('manual_products');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('storefront_collections', 'manual_products') === false) {
            Schema::table('storefront_collections', function (Blueprint $table) {
                $table->json('manual_products')->nullable();
            });
        }

        Schema::dropIfExists('storefront_collection_products');
    }
};
