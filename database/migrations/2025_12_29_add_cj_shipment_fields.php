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
        // Add CJ-specific fields to shipments, with existence checks
        Schema::table('shipments', function (Blueprint $table) {
            if (!Schema::hasColumn('shipments', 'cj_order_id')) {
                $table->string('cj_order_id')->nullable()->after('carrier')->comment('CJ order ID from createOrderV3');
            }
            if (!Schema::hasColumn('shipments', 'shipment_order_id')) {
                $table->string('shipment_order_id')->nullable()->after('cj_order_id')->comment('CJ shipment order ID');
            }
            if (!Schema::hasColumn('shipments', 'logistic_name')) {
                $table->string('logistic_name')->nullable()->after('shipment_order_id')->comment('Logistics method name (e.g., PostNL)');
            }
            if (!Schema::hasColumn('shipments', 'postage_amount')) {
                $table->decimal('postage_amount', 10, 2)->nullable()->after('logistic_name')->comment('Actual CJ postage/shipping cost');
            }
            if (!Schema::hasColumn('shipments', 'currency')) {
                $table->string('currency', 3)->nullable()->after('postage_amount')->comment('Currency of postage_amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // Drop columns individually to avoid passing array to hasColumn
            $columns = ['cj_order_id', 'shipment_order_id', 'logistic_name', 'postage_amount', 'currency'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('shipments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
