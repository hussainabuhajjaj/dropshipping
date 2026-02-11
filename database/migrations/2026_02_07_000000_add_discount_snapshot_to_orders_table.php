<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'discount_snapshot')) {
                $table->json('discount_snapshot')->nullable()->after('discount_total');
            }

            if (! Schema::hasColumn('orders', 'discount_source')) {
                $table->string('discount_source', 40)->nullable()->after('discount_snapshot');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'discount_source')) {
                $table->dropColumn('discount_source');
            }

            if (Schema::hasColumn('orders', 'discount_snapshot')) {
                $table->dropColumn('discount_snapshot');
            }
        });
    }
};

