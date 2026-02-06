<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_onboarding_settings', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 5)->default('en');
            $table->boolean('enabled')->default(true);
            $table->json('slides')->nullable();
            $table->timestamps();

            $table->unique('locale');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_onboarding_settings');
    }
};

