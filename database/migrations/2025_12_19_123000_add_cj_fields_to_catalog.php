<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'cj_id')) {
                $table->string('cj_id')->nullable()->unique()->after('id');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'cj_pid')) {
                $table->string('cj_pid')->nullable()->unique()->after('id');
            }
        });

        Schema::table('product_variants', function (Blueprint $table) {
            if (! Schema::hasColumn('product_variants', 'cj_vid')) {
                $table->string('cj_vid')->nullable()->unique()->after('id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'cj_id')) {
                $table->dropColumn('cj_id');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'cj_pid')) {
                $table->dropColumn('cj_pid');
            }
        });

        Schema::table('product_variants', function (Blueprint $table) {
            if (Schema::hasColumn('product_variants', 'cj_vid')) {
                $table->dropColumn('cj_vid');
            }
        });
    }
};
