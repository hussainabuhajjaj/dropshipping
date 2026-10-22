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
        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fulfillment_provider_id')->constrained()->cascadeOnDelete();
            $table->string('external_product_id');
            $table->string('external_sku')->nullable();
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->unsignedSmallInteger('lead_time_days')->default(0);
            $table->json('shipping_options')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['product_variant_id', 'fulfillment_provider_id', 'external_product_id'], 'supplier_products_unique_variant_provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_products');
    }
};
