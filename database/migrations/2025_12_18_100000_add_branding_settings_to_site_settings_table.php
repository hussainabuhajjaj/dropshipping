<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('site_name')->nullable();
            $table->text('site_description')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('meta_keywords')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('timezone')->default('UTC');
            $table->string('primary_color')->nullable();
            $table->string('secondary_color')->nullable();
            $table->string('accent_color')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
                'site_name',
                'site_description',
                'meta_title',
                'meta_description',
                'meta_keywords',
                'logo_path',
                'favicon_path',
                'timezone',
                'primary_color',
                'secondary_color',
                'accent_color',
            ]);
        });
    }
};
