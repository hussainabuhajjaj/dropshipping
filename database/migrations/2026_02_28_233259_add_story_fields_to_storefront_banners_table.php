<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('storefront_banners', function (Blueprint $table) {
            $table->string('story_type')->nullable()->after('display_type');
            $table->json('story_content')->nullable()->after('story_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('storefront_banners', function (Blueprint $table) {
            $table->dropColumn(['story_type', 'story_content']);
        });
    }
};
