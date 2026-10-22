<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_zone_id')->constrained('shipping_zones')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('min_weight', 10, 2)->nullable();
            $table->decimal('max_weight', 10, 2)->nullable();
            $table->decimal('min_price', 10, 2)->nullable();
            $table->decimal('max_price', 10, 2)->nullable();
            $table->decimal('rate', 10, 2);
            $table->string('carrier')->nullable();
            $table->unsignedSmallInteger('delivery_min_days')->nullable();
            $table->unsignedSmallInteger('delivery_max_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};
