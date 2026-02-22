<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('affiliates')) {
            Schema::create('affiliates', function (Blueprint $table) {
            $table->id();
            $table->string('referral_code')->unique();
            $table->enum('status', ['pending', 'approved', 'suspended']);
            $table->decimal('commission_rate', 5, 4)->nullable();
            $table->decimal('balance_pending', 12, 2)->default(0);
            $table->decimal('balance_available', 12, 2)->default(0);
            $table->decimal('total_earned', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['status']);
        });
        }

        if (! Schema::hasTable('affiliate_referrals')) {
            Schema::create('affiliate_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->string('visitor_token')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['affiliate_id', 'expires_at']);
        });
        }

        if (! Schema::hasTable('affiliate_commissions')) {
            Schema::create('affiliate_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->decimal('commission_rate', 5, 4);
            $table->decimal('commission_amount', 12, 2);
            $table->enum('status', ['pending', 'approved', 'rejected', 'paid']);
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->unique(['order_item_id']);
            $table->index(['affiliate_id', 'status']);
        });
        }

        if (! Schema::hasTable('affiliate_withdrawals')) {
            Schema::create('affiliate_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['pending', 'processed', 'rejected']);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['affiliate_id', 'status']);
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_withdrawals');
        Schema::dropIfExists('affiliate_commissions');
        Schema::dropIfExists('affiliate_referrals');
        Schema::dropIfExists('affiliates');
    }
};
