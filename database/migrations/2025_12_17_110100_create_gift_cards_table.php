<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('code')->unique();
            $table->decimal('balance', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('active'); // active, redeemed, expired
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_cards');
    }
};
