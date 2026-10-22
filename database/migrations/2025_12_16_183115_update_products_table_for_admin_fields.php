<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('selling_price', 12, 2)->nullable()->after('name');
            $table->decimal('cost_price', 12, 2)->nullable()->after('selling_price');
            $table->foreignId('supplier_id')->nullable()->after('default_fulfillment_provider_id')->constrained('fulfillment_providers')->nullOnDelete();
            $table->string('supplier_product_url')->nullable()->after('supplier_id');
            $table->unsignedSmallInteger('shipping_estimate_days')->nullable()->after('supplier_product_url');
            $table->boolean('is_active')->default(true)->after('shipping_estimate_days');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn([
                'selling_price',
                'cost_price',
                'supplier_id',
                'supplier_product_url',
                'shipping_estimate_days',
                'is_active',
            ]);
        });
    }
};
