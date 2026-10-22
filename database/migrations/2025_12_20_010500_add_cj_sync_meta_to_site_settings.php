<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('site_settings', 'cj_last_sync_at')) {
                $table->timestamp('cj_last_sync_at')->nullable()->after('auto_approve_review_days');
            }
            if (! Schema::hasColumn('site_settings', 'cj_last_sync_summary')) {
                $table->text('cj_last_sync_summary')->nullable()->after('cj_last_sync_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            if (Schema::hasColumn('site_settings', 'cj_last_sync_summary')) {
                $table->dropColumn('cj_last_sync_summary');
            }
            if (Schema::hasColumn('site_settings', 'cj_last_sync_at')) {
                $table->dropColumn('cj_last_sync_at');
            }
        });
    }
};
