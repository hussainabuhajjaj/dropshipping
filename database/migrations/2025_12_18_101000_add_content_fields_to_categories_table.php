<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->text('description')->nullable()->after('slug');
            $table->string('hero_title')->nullable()->after('description');
            $table->text('hero_subtitle')->nullable()->after('hero_title');
            $table->string('hero_image')->nullable()->after('hero_subtitle');
            $table->string('hero_cta_label')->nullable()->after('hero_image');
            $table->string('hero_cta_link')->nullable()->after('hero_cta_label');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn([
                'description',
                'hero_title',
                'hero_subtitle',
                'hero_image',
                'hero_cta_label',
                'hero_cta_link',
            ]);
        });
    }
};
