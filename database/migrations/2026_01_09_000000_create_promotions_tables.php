<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type'); // flash_sale, auto_discount, etc.
            $table->string('value_type'); // percentage, fixed, free_shipping, etc.
            $table->decimal('value', 12, 2);
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('stacking_rule')->default('combinable'); // combinable, exclusive
            $table->timestamps();
        });

        Schema::create('promotion_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->string('target_type'); // product, category, user_segment, cart
            $table->unsignedBigInteger('target_id');
            $table->timestamps();
        });

        Schema::create('promotion_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->string('condition_type'); // min_cart_value, min_qty, etc.
            $table->string('condition_value');
            $table->timestamps();
        });

        Schema::create('promotion_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('promotion_usages');
        Schema::dropIfExists('promotion_conditions');
        Schema::dropIfExists('promotion_targets');
        Schema::dropIfExists('promotions');
    }
};
