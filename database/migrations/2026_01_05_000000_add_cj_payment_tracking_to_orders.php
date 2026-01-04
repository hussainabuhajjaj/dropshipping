<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // CJ Order tracking
            $table->string('cj_order_id')->nullable()->after('grand_total');
            $table->string('cj_shipment_order_id')->nullable()->after('cj_order_id');
            $table->string('cj_order_status')->nullable()->after('cj_shipment_order_id'); // created, confirmed, ready
            $table->timestamp('cj_order_created_at')->nullable()->after('cj_order_status');
            $table->timestamp('cj_confirmed_at')->nullable()->after('cj_order_created_at');
            
            // CJ Payment tracking
            $table->string('cj_payment_status')->nullable()->default('pending')->after('cj_confirmed_at'); // pending, paid, failed
            $table->string('cj_pay_id')->nullable()->after('cj_payment_status');
            $table->decimal('cj_amount_due', 10, 2)->nullable()->after('cj_pay_id');
            $table->timestamp('cj_paid_at')->nullable()->after('cj_amount_due');
            $table->text('cj_payment_error')->nullable()->after('cj_paid_at');
            
            // Idempotency
            $table->string('cj_payment_idempotency_key')->nullable()->after('cj_payment_error');
            $table->integer('cj_payment_attempts')->default(0)->after('cj_payment_idempotency_key');
            
            // Indexes
            $table->index(['cj_payment_status', 'payment_status']);
            $table->index(['cj_order_id']);
            $table->unique(['cj_payment_idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'cj_order_id',
                'cj_shipment_order_id',
                'cj_order_status',
                'cj_order_created_at',
                'cj_confirmed_at',
                'cj_payment_status',
                'cj_pay_id',
                'cj_amount_due',
                'cj_paid_at',
                'cj_payment_error',
                'cj_payment_idempotency_key',
                'cj_payment_attempts',
            ]);
            $table->dropIndex(['cj_payment_status', 'payment_status']);
            $table->dropIndex(['cj_order_id']);
            $table->dropUnique(['cj_payment_idempotency_key']);
        });
    }
};
