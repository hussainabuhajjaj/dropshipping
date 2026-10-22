<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            if (! Schema::hasColumn('product_variants', 'stock_on_hand')) {
                $table->unsignedInteger('stock_on_hand')->nullable()->after('inventory_policy');
            }
            if (! Schema::hasColumn('product_variants', 'low_stock_threshold')) {
                $table->unsignedInteger('low_stock_threshold')->default(5)->after('stock_on_hand');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            if (Schema::hasColumn('product_variants', 'low_stock_threshold')) {
                $table->dropColumn('low_stock_threshold');
            }
            if (Schema::hasColumn('product_variants', 'stock_on_hand')) {
                $table->dropColumn('stock_on_hand');
            }
        });
    }
};
