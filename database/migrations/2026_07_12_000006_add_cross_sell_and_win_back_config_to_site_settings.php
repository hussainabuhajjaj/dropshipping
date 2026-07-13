<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('site_settings', 'cross_sell_config')) {
                $table->json('cross_sell_config')->nullable()->after('abandoned_cart_config');
            }
            if (! Schema::hasColumn('site_settings', 'win_back_config')) {
                $table->json('win_back_config')->nullable()->after('cross_sell_config');
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $columns = array_filter(['cross_sell_config', 'win_back_config'], fn ($col) => Schema::hasColumn('site_settings', $col));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
