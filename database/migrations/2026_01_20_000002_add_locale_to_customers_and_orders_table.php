<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'locale')) {
                $table->string('locale', 5)->nullable()->after('email');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'locale')) {
                $table->string('locale', 5)->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'locale')) {
                $table->dropColumn('locale');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'locale')) {
                $table->dropColumn('locale');
            }
        });
    }
};
