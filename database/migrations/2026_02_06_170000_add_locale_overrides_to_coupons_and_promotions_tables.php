<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            if (! Schema::hasColumn('coupons', 'locale_overrides')) {
                $table->json('locale_overrides')->nullable()->after('meta');
            }
        });

        Schema::table('promotions', function (Blueprint $table) {
            if (! Schema::hasColumn('promotions', 'locale_overrides')) {
                $table->json('locale_overrides')->nullable()->after('stacking_rule');
            }
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            if (Schema::hasColumn('coupons', 'locale_overrides')) {
                $table->dropColumn('locale_overrides');
            }
        });

        Schema::table('promotions', function (Blueprint $table) {
            if (Schema::hasColumn('promotions', 'locale_overrides')) {
                $table->dropColumn('locale_overrides');
            }
        });
    }
};

