<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'local_warehouse_id')) {
                $table->foreignId('local_warehouse_id')
                    ->nullable()
                    ->after('cost_price')
                    ->constrained('local_ware_houses')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('products', 'pricing_meta')) {
                $table->json('pricing_meta')->nullable()->after('local_warehouse_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'local_warehouse_id')) {
                $table->dropConstrainedForeignId('local_warehouse_id');
            }

            if (Schema::hasColumn('products', 'pricing_meta')) {
                $table->dropColumn('pricing_meta');
            }
        });
    }
};
