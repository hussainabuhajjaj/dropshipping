<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_collections', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('type')->default('collection'); // collection, guide, seasonal, drop
            $table->text('description')->nullable();
            $table->string('hero_kicker')->nullable();
            $table->text('hero_subtitle')->nullable();
            $table->string('hero_image')->nullable();
            $table->string('hero_cta_label')->nullable();
            $table->string('hero_cta_url')->nullable();
            $table->longText('content')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('timezone')->nullable();
            $table->json('locale_visibility')->nullable();
            $table->json('locale_overrides')->nullable();
            $table->string('selection_mode')->default('rules'); // rules, manual, hybrid
            $table->json('rules')->nullable();
            $table->json('manual_products')->nullable();
            $table->unsignedInteger('product_limit')->nullable();
            $table->string('sort_by')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_collections');
    }
};
