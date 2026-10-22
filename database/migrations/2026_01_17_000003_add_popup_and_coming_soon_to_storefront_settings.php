<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('storefront_settings', 'coming_soon_enabled')) {
                $table->boolean('coming_soon_enabled')->default(false)->after('social_links');
                $table->string('coming_soon_title', 160)->nullable()->after('coming_soon_enabled');
                $table->text('coming_soon_message')->nullable()->after('coming_soon_title');
                $table->string('coming_soon_image')->nullable()->after('coming_soon_message');
                $table->string('coming_soon_cta_label', 80)->nullable()->after('coming_soon_image');
                $table->string('coming_soon_cta_url')->nullable()->after('coming_soon_cta_label');
            }

            if (! Schema::hasColumn('storefront_settings', 'newsletter_popup_enabled')) {
                $table->boolean('newsletter_popup_enabled')->default(false)->after('coming_soon_cta_url');
                $table->string('newsletter_popup_title', 160)->nullable()->after('newsletter_popup_enabled');
                $table->text('newsletter_popup_body')->nullable()->after('newsletter_popup_title');
                $table->string('newsletter_popup_incentive', 160)->nullable()->after('newsletter_popup_body');
                $table->string('newsletter_popup_image')->nullable()->after('newsletter_popup_incentive');
                $table->unsignedInteger('newsletter_popup_delay_seconds')->default(3)->after('newsletter_popup_image');
                $table->unsignedInteger('newsletter_popup_dismiss_days')->default(14)->after('newsletter_popup_delay_seconds');
            }
        });
    }

    public function down(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table) {
            $columns = [
                'coming_soon_enabled',
                'coming_soon_title',
                'coming_soon_message',
                'coming_soon_image',
                'coming_soon_cta_label',
                'coming_soon_cta_url',
                'newsletter_popup_enabled',
                'newsletter_popup_title',
                'newsletter_popup_body',
                'newsletter_popup_incentive',
                'newsletter_popup_image',
                'newsletter_popup_delay_seconds',
                'newsletter_popup_dismiss_days',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('storefront_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
