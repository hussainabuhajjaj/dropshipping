<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('cj_warehouse_id')->nullable()->after('cj_pid');
            $table->string('cj_warehouse_name')->nullable()->after('cj_warehouse_id');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->json('cj_variant_data')->nullable()->after('cj_vid');
            $table->integer('cj_stock')->default(0)->after('cj_variant_data');
            $table->timestamp('cj_stock_synced_at')->nullable()->after('cj_stock');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['cj_warehouse_id', 'cj_warehouse_name']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['cj_variant_data', 'cj_stock', 'cj_stock_synced_at']);
        });
    }
};
