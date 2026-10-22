<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->decimal('min_cart_total', 12, 2)->default(25.00)->after('free_shipping_threshold');
            $table->boolean('min_cart_total_enabled')->default(true)->after('min_cart_total');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['min_cart_total', 'min_cart_total_enabled']);
        });
    }
};
