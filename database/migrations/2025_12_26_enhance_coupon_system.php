<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            // Add coupon targeting columns
            $table->string('applicable_to')->default('all')->after('type'); // all, products, categories
            $table->boolean('exclude_on_sale')->default(false)->after('applicable_to');
            $table->boolean('is_one_time_per_customer')->default(false)->after('exclude_on_sale');
        });

        // Create pivot table for coupon-product relationships
        Schema::create('coupon_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['coupon_id', 'product_id']);
        });

        // Create pivot table for coupon-category relationships
        Schema::create('coupon_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['coupon_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_category');
        Schema::dropIfExists('coupon_product');
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['applicable_to', 'exclude_on_sale', 'is_one_time_per_customer']);
        });
    }
};
