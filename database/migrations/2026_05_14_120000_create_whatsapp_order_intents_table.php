<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_order_intents', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->string('status', 24)->default('pending')->index();
            $table->string('channel', 24)->default('web')->index();
            $table->string('intent_type', 24)->index();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id')->nullable()->index();
            $table->string('guest_token', 120)->nullable()->index();
            $table->string('phone', 30)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('items_count')->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('shipping_total', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->json('snapshot');
            $table->string('pricing_hash', 64)->index();
            $table->unsignedSmallInteger('snapshot_version')->default(1);
            $table->string('source_url')->nullable();
            $table->text('user_agent')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('converted_at')->nullable()->index();
            $table->foreignId('converted_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('converted_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('last_error_code', 80)->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['customer_id', 'created_at']);
            $table->index(['session_id', 'created_at']);
            $table->index(['guest_token', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_order_intents');
    }
};
