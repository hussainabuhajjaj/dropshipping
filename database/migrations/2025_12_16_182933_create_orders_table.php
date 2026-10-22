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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->index();
            $table->string('status')->default('pending'); // pending, paid, fulfilling, fulfilled, cancelled, refunded
            $table->string('payment_status')->default('unpaid'); // unpaid, paid, refunded
            $table->string('currency', 3)->default('USD');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('shipping_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->foreignId('shipping_address_id')->constrained('addresses')->cascadeOnDelete();
            $table->foreignId('billing_address_id')->nullable()->constrained('addresses')->nullOnDelete();
            $table->string('shipping_method')->nullable();
            $table->string('delivery_notes')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'payment_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
