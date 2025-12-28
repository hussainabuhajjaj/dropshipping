<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_banners', function (Blueprint $table) {
            $table->id();
            $table->string('title')->index();
            $table->text('description')->nullable();
            $table->string('type')->default('promotion'); // promotion, event, seasonal, flash_sale
            $table->string('display_type')->default('hero'); // hero, sidebar, carousel, strip
            $table->string('target_type')->nullable(); // product, category, url, none
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('external_url')->nullable();
            $table->string('image_path')->nullable();
            $table->string('background_color')->default('#ffffff');
            $table->string('text_color')->default('#000000');
            $table->string('badge_text')->nullable();
            $table->string('badge_color')->default('primary');
            $table->string('cta_text')->nullable();
            $table->string('cta_url')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('display_order')->default(0);
            $table->json('targeting')->nullable(); // devices, user_types, locations, etc.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_banners');
    }
};
