<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('woocommerce_product_maps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('woocommerce_product_id');
            $table->unsignedBigInteger('woocommerce_variation_id')->nullable();
            $table->string('sku')->nullable()->index();
            $table->string('sync_hash', 64)->nullable();
            $table->string('status')->default('pending');
            $table->text('last_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->nullOnDelete();

            $table->foreign('product_variant_id')
                ->references('id')
                ->on('product_variants')
                ->nullOnDelete();

            $table->unique(['woocommerce_product_id', 'woocommerce_variation_id'], 'wc_product_map_unique');
            $table->index('product_id');
            $table->index('product_variant_id');
            $table->index('status');
        });

        Schema::create('woocommerce_customer_maps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('woocommerce_customer_id');
            $table->string('email')->index();
            $table->string('status')->default('synced');
            $table->text('last_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->cascadeOnDelete();

            $table->unique('woocommerce_customer_id');
            $table->unique('customer_id');
        });

        Schema::create('woocommerce_order_maps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('woocommerce_order_id');
            $table->string('woocommerce_order_number')->nullable();
            $table->string('status')->default('pending');
            $table->text('last_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->cascadeOnDelete();

            $table->unique('woocommerce_order_id');
            $table->unique('order_id');
            $table->index('status');
        });

        Schema::create('woocommerce_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('webhook_id')->nullable()->index();
            $table->string('delivery_id')->nullable()->unique();
            $table->string('event_type');
            $table->string('resource')->nullable();
            $table->string('event')->nullable();
            $table->json('payload');
            $table->string('status')->default('received');
            $table->text('last_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('event_type');
            $table->index('status');
            $table->index('created_at');
        });

        Schema::create('woocommerce_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('action');
            $table->string('status')->default('pending');
            $table->text('request_summary')->nullable();
            $table->text('response_summary')->nullable();
            $table->text('error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['entity_type', 'entity_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('woocommerce_sync_logs');
        Schema::dropIfExists('woocommerce_webhook_logs');
        Schema::dropIfExists('woocommerce_order_maps');
        Schema::dropIfExists('woocommerce_customer_maps');
        Schema::dropIfExists('woocommerce_product_maps');
    }
};
