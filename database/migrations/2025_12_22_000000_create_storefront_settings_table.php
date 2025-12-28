<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_settings', function (Blueprint $table) {
            $table->id();
            $table->string('brand_name')->nullable();
            $table->string('footer_blurb', 500)->nullable();
            $table->string('delivery_notice', 255)->nullable();
            $table->string('copyright_text', 255)->nullable();
            $table->json('header_links')->nullable();
            $table->json('footer_columns')->nullable();
            $table->json('value_props')->nullable();
            $table->json('social_links')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_settings');
    }
};
