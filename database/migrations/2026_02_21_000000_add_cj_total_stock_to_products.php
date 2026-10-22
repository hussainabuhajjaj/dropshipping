<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (!Schema::hasColumn('products', 'cj_total_stock')) {
                $table->integer('cj_total_stock')->default(0)->after('stock_on_hand');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'cj_total_stock')) {
                $table->dropColumn('cj_total_stock');
            }
        });
    }
};
