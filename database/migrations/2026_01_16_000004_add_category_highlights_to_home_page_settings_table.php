<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('home_page_settings', function (Blueprint $table) {
            $table->json('category_highlights')->nullable()->after('rail_cards');
        });
    }

    public function down(): void
    {
        Schema::table('home_page_settings', function (Blueprint $table) {
            $table->dropColumn('category_highlights');
        });
    }
};
