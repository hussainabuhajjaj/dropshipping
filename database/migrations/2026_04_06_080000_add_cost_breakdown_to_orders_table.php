<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('supplier_product_cost_total', 12, 2)->nullable()->after('shipping_variance');
            $table->decimal('supplier_external_shipping_total', 12, 2)->nullable()->after('supplier_product_cost_total');
            $table->decimal('supplier_cj_shipping_total', 12, 2)->nullable()->after('supplier_external_shipping_total');
            $table->decimal('supplier_total_cost', 12, 2)->nullable()->after('supplier_cj_shipping_total');
            $table->decimal('gross_profit_amount', 12, 2)->nullable()->after('supplier_total_cost');
            $table->decimal('gross_margin_percent', 8, 2)->nullable()->after('gross_profit_amount');
            $table->json('cost_breakdown')->nullable()->after('gross_margin_percent');
            $table->timestamp('cost_calculated_at')->nullable()->after('cost_breakdown');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'supplier_product_cost_total',
                'supplier_external_shipping_total',
                'supplier_cj_shipping_total',
                'supplier_total_cost',
                'gross_profit_amount',
                'gross_margin_percent',
                'cost_breakdown',
                'cost_calculated_at',
            ]);
        });
    }
};
