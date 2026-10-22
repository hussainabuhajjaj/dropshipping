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
            if (! Schema::hasColumn('products', 'cj_sync_enabled')) {
                $table->boolean('cj_sync_enabled')->default(false)->after('cj_pid');
            }
            if (! Schema::hasColumn('products', 'cj_synced_at')) {
                $table->timestamp('cj_synced_at')->nullable()->after('cj_sync_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'cj_synced_at')) {
                $table->dropColumn('cj_synced_at');
            }
            if (Schema::hasColumn('products', 'cj_sync_enabled')) {
                $table->dropColumn('cj_sync_enabled');
            }
        });
    }
};
