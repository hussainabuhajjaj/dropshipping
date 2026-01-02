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
        // Add order-level shipping reconciliation fields
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('shipping_total_estimated', 10, 2)->nullable()->after('shipping_total')->comment('Shipping quoted at checkout');
            $table->decimal('shipping_total_actual', 10, 2)->nullable()->after('shipping_total_estimated')->comment('Sum of actual CJ postage from shipments');
            $table->decimal('shipping_variance', 10, 2)->nullable()->after('shipping_total_actual')->comment('Difference: actual - estimated');
            $table->timestamp('shipping_reconciled_at')->nullable()->after('shipping_variance')->comment('When reconciliation was last updated');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['shipping_total_estimated', 'shipping_total_actual', 'shipping_variance', 'shipping_reconciled_at']);
        });
    }
};
