<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_collections', function (Blueprint $table): void {
            $table->index(['is_active', 'display_order'], 'idx_storefront_collections_active_order');
        });

        Schema::table('storefront_collection_products', function (Blueprint $table): void {
            $table->index(
                ['storefront_collection_id', 'position', 'product_id'],
                'idx_storefront_collection_products_collection_position_product'
            );
        });
    }

    public function down(): void
    {
        Schema::table('storefront_collection_products', function (Blueprint $table): void {
            $table->dropIndex('idx_storefront_collection_products_collection_position_product');
        });

        Schema::table('storefront_collections', function (Blueprint $table): void {
            $table->dropIndex('idx_storefront_collections_active_order');
        });
    }
};
