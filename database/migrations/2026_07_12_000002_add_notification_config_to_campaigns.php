<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_campaigns', function (Blueprint $table) {
            $table->json('notification_config')->nullable()->after('newsletter_campaign_ids')
                ->comment('{"on_start":{"push":true,"email":true,"whatsapp":true},"on_ending_soon":{"push":true,"email":false,"whatsapp":false,"hours_before":24},"on_end":{"push":false,"email":false,"whatsapp":false}}');
            $table->json('sourcing_config')->nullable()->after('notification_config')
                ->comment('{"enabled":true,"sourcing_days_before":7,"auto_create_collection":true,"override_home_sections":["featured"]}');
        });
    }

    public function down(): void
    {
        Schema::table('storefront_campaigns', function (Blueprint $table) {
            $table->dropColumn(['notification_config', 'sourcing_config']);
        });
    }
};
