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
            if (! Schema::hasColumn('home_page_settings', 'app_download')) {
                $table->json('app_download')->nullable()->after('banner_strip');
            }
        });
    }

    public function down(): void
    {
        Schema::table('home_page_settings', function (Blueprint $table) {
            if (Schema::hasColumn('home_page_settings', 'app_download')) {
                $table->dropColumn('app_download');
            }
        });
    }
};
