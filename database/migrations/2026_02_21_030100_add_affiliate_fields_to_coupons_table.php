<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->boolean('affects_affiliate_commission')->default(true);
            $table->foreignId('affiliate_id')->nullable()->constrained()->nullOnDelete();
            $table->index(['affects_affiliate_commission', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropIndex(['affects_affiliate_commission', 'is_active']);
            $table->dropForeign(['affiliate_id']);
            $table->dropColumn(['affects_affiliate_commission', 'affiliate_id']);
        });
    }
};
