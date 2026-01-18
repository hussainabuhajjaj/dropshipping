<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->string('promotion_intent')->default('other')->after('stacking_rule');
            $table->json('display_placements')->nullable()->after('promotion_intent');
        });

        Schema::table('promotion_usages', function (Blueprint $table) {
            $table->decimal('discount_amount', 12, 2)->nullable()->after('order_id');
            $table->json('meta')->nullable()->after('used_at');
        });

        // Backfill intents for existing data with best-effort defaults.
        DB::table('promotions')
            ->where('type', 'flash_sale')
            ->update(['promotion_intent' => 'urgency']);

        DB::table('promotions')
            ->where('type', 'auto_discount')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('promotion_conditions')
                    ->whereColumn('promotion_conditions.promotion_id', 'promotions.id')
                    ->where('promotion_conditions.condition_type', 'min_cart_value')
                    ->whereRaw('CAST(promotion_conditions.condition_value AS DECIMAL(12,2)) >= 20');
            })
            ->update(['promotion_intent' => 'shipping_support']);

        DB::table('promotions')
            ->where('promotion_intent', 'other')
            ->where('type', 'auto_discount')
            ->update(['promotion_intent' => 'cart_growth']);
    }

    public function down(): void
    {
        Schema::table('promotion_usages', function (Blueprint $table) {
            $table->dropColumn(['discount_amount', 'meta']);
        });

        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn(['promotion_intent', 'display_placements']);
        });
    }
};
