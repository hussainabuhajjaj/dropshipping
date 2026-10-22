<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'cj_stock_synced_at')) {
                $table->timestamp('cj_stock_synced_at')->nullable()->after('cj_synced_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'cj_stock_synced_at')) {
                $table->dropColumn('cj_stock_synced_at');
            }
        });
    }
};
