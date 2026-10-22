<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('provider'); // mobile_money, card, etc.
            $table->string('status')->default('pending'); // pending, authorized, paid, failed, refunded
            $table->string('provider_reference')->nullable(); // transaction id from provider
            $table->string('idempotency_key')->nullable(); // provider event idempotency
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->json('meta')->nullable(); // webhook payloads, receipts
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_reference']);
            $table->unique(['provider', 'idempotency_key']);
            $table->index(['status', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
