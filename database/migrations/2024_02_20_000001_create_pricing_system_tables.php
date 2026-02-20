<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Create pricing configuration cache table for performance
        Schema::create('pricing_config_cache', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value');
            $table->timestamp('expires_at');
            $table->timestamps();
            
            $table->index(['key', 'expires_at']);
        });

        // Create pricing operation logs for monitoring
        Schema::create('pricing_operation_logs', function (Blueprint $table) {
            $table->id();
            $table->string('operation_type'); // 'product_update', 'bulk_update', 'variant_sync'
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->json('input_data')->nullable();
            $table->json('result_data')->nullable();
            $table->string('status'); // 'success', 'error', 'partial'
            $table->text('error_message')->nullable();
            $table->float('execution_time_ms')->default(0);
            $table->timestamps();
            
            $table->index(['operation_type', 'created_at']);
            $table->index(['product_id', 'created_at']);
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });

        // Add indexes to products table for better pricing query performance
        Schema::table('products', function (Blueprint $table) {
            $table->index(['cost_price', 'currency', 'category_id'], 'pricing_query_index');
            $table->index(['cj_lock_price', 'updated_at'], 'pricing_lock_index');
        });

        // Add indexes to product_variants table for better pricing query performance
        Schema::table('product_variants', function (Blueprint $table) {
            $table->index(['product_id', 'cost_price', 'currency'], 'variant_pricing_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('pricing_config_cache');
        Schema::dropIfExists('pricing_operation_logs');
        
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('pricing_query_index');
            $table->dropIndex('pricing_lock_index');
        });
        
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex('variant_pricing_index');
        });
    }
};
