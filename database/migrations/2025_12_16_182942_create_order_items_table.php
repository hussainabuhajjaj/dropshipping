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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fulfillment_provider_id')->nullable()->constrained('fulfillment_providers')->nullOnDelete();
            $table->foreignId('supplier_product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('fulfillment_status')->default('pending'); // pending, awaiting_fulfillment, fulfilling, fulfilled, failed
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total', 12, 2);
            $table->string('source_sku')->nullable();
            $table->json('snapshot')->nullable(); // product data at time of order
            $table->json('meta')->nullable(); // per-provider custom data
            $table->timestamps();
            $table->index(['fulfillment_provider_id', 'fulfillment_status'], 'order_items_provider_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
