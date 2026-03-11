<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('typesense_search_key')->nullable()->after('locale_overrides');
            $table->string('typesense_admin_key')->nullable()->after('typesense_search_key');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['typesense_search_key', 'typesense_admin_key']);
        });
    }
};
