<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_campaign_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qr_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->timestamp('claimed_at')->useCurrent();
            $table->boolean('reward_delivered')->default(false);
            $table->timestamp('reward_delivered_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['qr_campaign_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_campaign_claims');
    }
};
