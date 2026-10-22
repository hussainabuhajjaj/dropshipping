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
            $table->boolean('auto_approve_reviews')->default(false)->after('customs_message');
            $table->unsignedInteger('auto_approve_review_days')->default(0)->after('auto_approve_reviews');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['auto_approve_reviews', 'auto_approve_review_days']);
        });
    }
};
