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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->string('title');
            $table->decimal('price', 12, 2);
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('weight_grams')->nullable();
            $table->unsignedInteger('package_length_mm')->nullable();
            $table->unsignedInteger('package_width_mm')->nullable();
            $table->unsignedInteger('package_height_mm')->nullable();
            $table->string('inventory_policy')->default('allow'); // allow, deny
            $table->json('options')->nullable(); // variant option values snapshot
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
