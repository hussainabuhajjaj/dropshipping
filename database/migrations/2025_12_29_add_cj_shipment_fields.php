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
        // Add CJ-specific fields to shipments
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('cj_order_id')->nullable()->after('carrier')->comment('CJ order ID from createOrderV3');
            $table->string('shipment_order_id')->nullable()->after('cj_order_id')->comment('CJ shipment order ID');
            $table->string('logistic_name')->nullable()->after('shipment_order_id')->comment('Logistics method name (e.g., PostNL)');
            $table->decimal('postage_amount', 10, 2)->nullable()->after('logistic_name')->comment('Actual CJ postage/shipping cost');
            $table->string('currency', 3)->nullable()->after('postage_amount')->comment('Currency of postage_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['cj_order_id', 'shipment_order_id', 'logistic_name', 'postage_amount', 'currency']);
        });
    }
};
