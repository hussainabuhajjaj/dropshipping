<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('site_settings', 'tax_label')) {
                $table->string('tax_label', 60)->nullable();
            }
            if (! Schema::hasColumn('site_settings', 'tax_rate')) {
                $table->decimal('tax_rate', 8, 2)->default(0);
            }
            if (! Schema::hasColumn('site_settings', 'tax_included')) {
                $table->boolean('tax_included')->default(false);
            }
            if (! Schema::hasColumn('site_settings', 'shipping_handling_fee')) {
                $table->decimal('shipping_handling_fee', 10, 2)->default(0);
            }
            if (! Schema::hasColumn('site_settings', 'free_shipping_threshold')) {
                $table->decimal('free_shipping_threshold', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('site_settings', 'shipping_policy')) {
                $table->longText('shipping_policy')->nullable();
            }
            if (! Schema::hasColumn('site_settings', 'refund_policy')) {
                $table->longText('refund_policy')->nullable();
            }
            if (! Schema::hasColumn('site_settings', 'privacy_policy')) {
                $table->longText('privacy_policy')->nullable();
            }
            if (! Schema::hasColumn('site_settings', 'terms_of_service')) {
                $table->longText('terms_of_service')->nullable();
            }
            if (! Schema::hasColumn('site_settings', 'customs_disclaimer')) {
                $table->longText('customs_disclaimer')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $columns = [
                'tax_label',
                'tax_rate',
                'tax_included',
                'shipping_handling_fee',
                'free_shipping_threshold',
                'shipping_policy',
                'refund_policy',
                'privacy_policy',
                'terms_of_service',
                'customs_disclaimer',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('site_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
