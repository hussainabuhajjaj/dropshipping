<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->default('seasonal'); // seasonal, drop, event
            $table->string('status')->default('draft'); // draft, scheduled, active, ended
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('timezone')->nullable();
            $table->json('locale_visibility')->nullable();
            $table->json('locale_overrides')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->string('stacking_mode')->default('stackable'); // stackable, exclusive
            $table->string('exclusive_group')->nullable();
            $table->json('theme')->nullable();
            $table->json('placements')->nullable();
            $table->string('hero_image')->nullable();
            $table->string('hero_kicker')->nullable();
            $table->text('hero_subtitle')->nullable();
            $table->longText('content')->nullable();
            $table->json('promotion_ids')->nullable();
            $table->json('coupon_ids')->nullable();
            $table->json('banner_ids')->nullable();
            $table->json('collection_ids')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_campaigns');
    }
};
