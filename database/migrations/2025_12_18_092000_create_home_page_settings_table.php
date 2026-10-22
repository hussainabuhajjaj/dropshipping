<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_page_settings', function (Blueprint $table) {
            $table->id();
            $table->json('top_strip')->nullable();
            $table->json('hero_slides')->nullable();
            $table->json('rail_cards')->nullable();
            $table->json('banner_strip')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_page_settings');
    }
};
