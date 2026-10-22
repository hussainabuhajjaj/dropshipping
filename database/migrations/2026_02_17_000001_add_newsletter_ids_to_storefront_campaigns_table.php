<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('storefront_campaigns', 'newsletter_campaign_ids')) {
                $table->json('newsletter_campaign_ids')->nullable()->after('collection_ids');
            }
        });
    }

    public function down(): void
    {
        Schema::table('storefront_campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('storefront_campaigns', 'newsletter_campaign_ids')) {
                $table->dropColumn('newsletter_campaign_ids');
            }
        });
    }
};
