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
            $table->json('lucky_draw_config')->nullable()->after('sourcing_config')
                ->comment('{"min_order_amount":30000,"currency":"XOF","max_participants":50,"grand_prize":"iPhone 17 Pro Max","runner_up_count":10,"gift_card_amount":20,"gift_card_currency":"USD","guaranteed_reward_type":"coupon_code","guaranteed_reward_value":10,"show_remaining_spots":true,"countdown_enabled":true,"winner_announcement_at":null,"terms":null,"faq":[],"seo":[],"landing_content":null,"cta":null}');
        });

        Schema::create('campaign_participations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('storefront_campaigns')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('spot_number')->nullable();
            $table->string('state', 30)->default('qualified')
                ->comment('qualified|spot_reserved|reward_issued|winner');
            $table->string('guaranteed_reward_type', 30)->nullable()
                ->comment('free_shipping|percentage_discount|fixed_discount|store_credit|coupon_code');
            $table->decimal('guaranteed_reward_value', 12, 2)->nullable();
            $table->string('reward_code')->nullable();
            $table->timestamp('reward_issued_at')->nullable();
            $table->timestamp('qualified_at')->useCurrent();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['campaign_id', 'customer_id']);
            $table->index(['campaign_id', 'spot_number']);
            $table->index(['campaign_id', 'state']);
        });

        Schema::create('campaign_participation_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participation_id')->constrained('campaign_participations')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->decimal('order_total', 12, 2)->nullable();
            $table->timestamp('qualified_at')->useCurrent();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['participation_id', 'order_id']);
            $table->index(['order_id']);
        });

        Schema::create('campaign_winners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('storefront_campaigns')->cascadeOnDelete();
            $table->foreignId('participation_id')->constrained('campaign_participations')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('prize_type', 20)->comment('grand|runner_up|guaranteed');
            $table->decimal('prize_value', 12, 2)->nullable();
            $table->string('prize_label')->nullable();
            $table->string('reward_code')->nullable();
            $table->string('status', 20)->default('pending')
                ->comment('pending|delivered|fulfilled|expired');
            $table->timestamp('announced_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'participation_id']);
            $table->index(['campaign_id', 'prize_type']);
            $table->index(['campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_winners');
        Schema::dropIfExists('campaign_participation_orders');
        Schema::dropIfExists('campaign_participations');

        Schema::table('storefront_campaigns', function (Blueprint $table) {
            $table->dropColumn('lucky_draw_config');
        });
    }
};
