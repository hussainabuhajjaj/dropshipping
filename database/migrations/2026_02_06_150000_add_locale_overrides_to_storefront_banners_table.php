<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_banners', function (Blueprint $table) {
            if (! Schema::hasColumn('storefront_banners', 'locale_overrides')) {
                $table->json('locale_overrides')->nullable()->after('targeting');
            }
        });
    }

    public function down(): void
    {
        Schema::table('storefront_banners', function (Blueprint $table) {
            if (Schema::hasColumn('storefront_banners', 'locale_overrides')) {
                $table->dropColumn('locale_overrides');
            }
        });
    }
};

