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
            if (! Schema::hasColumn('products', 'cj_last_payload')) {
                $table->json('cj_last_payload')->nullable()->after('cj_synced_at');
            }
            if (! Schema::hasColumn('products', 'cj_last_changed_fields')) {
                $table->json('cj_last_changed_fields')->nullable()->after('cj_last_payload');
            }
            if (! Schema::hasColumn('products', 'cj_lock_price')) {
                $table->boolean('cj_lock_price')->default(false)->after('cj_last_changed_fields');
            }
            if (! Schema::hasColumn('products', 'cj_lock_description')) {
                $table->boolean('cj_lock_description')->default(false)->after('cj_lock_price');
            }
            if (! Schema::hasColumn('products', 'cj_lock_images')) {
                $table->boolean('cj_lock_images')->default(false)->after('cj_lock_description');
            }
            if (! Schema::hasColumn('products', 'cj_lock_variants')) {
                $table->boolean('cj_lock_variants')->default(false)->after('cj_lock_images');
            }
            if (! Schema::hasColumn('products', 'stock_on_hand')) {
                $table->unsignedInteger('stock_on_hand')->nullable()->after('cj_lock_variants');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'stock_on_hand')) {
                $table->dropColumn('stock_on_hand');
            }
            if (Schema::hasColumn('products', 'cj_lock_variants')) {
                $table->dropColumn('cj_lock_variants');
            }
            if (Schema::hasColumn('products', 'cj_lock_images')) {
                $table->dropColumn('cj_lock_images');
            }
            if (Schema::hasColumn('products', 'cj_lock_description')) {
                $table->dropColumn('cj_lock_description');
            }
            if (Schema::hasColumn('products', 'cj_lock_price')) {
                $table->dropColumn('cj_lock_price');
            }
            if (Schema::hasColumn('products', 'cj_last_changed_fields')) {
                $table->dropColumn('cj_last_changed_fields');
            }
            if (Schema::hasColumn('products', 'cj_last_payload')) {
                $table->dropColumn('cj_last_payload');
            }
        });
    }
};
