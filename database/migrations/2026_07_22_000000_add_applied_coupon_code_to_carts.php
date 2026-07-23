<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('carts', 'applied_coupon_code')) {
            Schema::table('carts', function (Blueprint $table) {
                $table->string('applied_coupon_code')->nullable()->after('visitor_id');
                $table->json('applied_coupon_data')->nullable()->after('applied_coupon_code');
            });
        }
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn(['applied_coupon_code', 'applied_coupon_data']);
        });
    }
};
