<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_campaign_logs', function (Blueprint $table) {
            $table->string('tracking_token', 64)->nullable()->unique()->after('email');
            $table->timestamp('opened_at')->nullable()->after('sent_at');
            $table->timestamp('clicked_at')->nullable()->after('opened_at');
            $table->unsignedInteger('click_count')->default(0)->after('clicked_at');
        });
    }

    public function down(): void
    {
        Schema::table('newsletter_campaign_logs', function (Blueprint $table) {
            $table->dropUnique(['tracking_token']);
            $table->dropColumn(['tracking_token', 'opened_at', 'clicked_at', 'click_count']);
        });
    }
};
