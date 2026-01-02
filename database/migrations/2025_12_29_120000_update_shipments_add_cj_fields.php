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
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('cj_order_id')->nullable()->after('order_item_id');
            $table->string('shipment_order_id')->nullable()->after('cj_order_id');
            $table->string('logistic_name')->nullable()->after('carrier');
            $table->decimal('postage_amount', 10, 2)->nullable()->after('tracking_url');
            $table->string('currency', 3)->nullable()->after('postage_amount');
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
