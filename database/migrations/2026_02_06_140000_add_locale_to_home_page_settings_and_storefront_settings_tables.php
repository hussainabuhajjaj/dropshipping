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
            if (! Schema::hasColumn('home_page_settings', 'locale')) {
                $table->string('locale', 5)->nullable()->after('id')->index();
            }
        });

        Schema::table('storefront_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('storefront_settings', 'locale')) {
                $table->string('locale', 5)->nullable()->after('id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('home_page_settings', function (Blueprint $table) {
            if (Schema::hasColumn('home_page_settings', 'locale')) {
                $table->dropColumn('locale');
            }
        });

        Schema::table('storefront_settings', function (Blueprint $table) {
            if (Schema::hasColumn('storefront_settings', 'locale')) {
                $table->dropColumn('locale');
            }
        });
    }
};

