<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('linehaul_shipments', function (Blueprint $table) {
            $table->string('cj_order_id')->nullable()->after('order_id');
            $table->string('cj_order_num')->nullable()->after('cj_order_id');
            $table->string('cj_order_status')->nullable()->after('cj_order_num');
            $table->decimal('cj_order_amount', 18, 2)->nullable()->after('cj_order_status');
            $table->decimal('cj_product_amount', 18, 2)->nullable()->after('cj_order_amount');
            $table->decimal('cj_postage_amount', 18, 2)->nullable()->after('cj_product_amount');
            $table->decimal('cj_order_weight', 10, 3)->nullable()->after('cj_postage_amount');
            $table->string('cj_logistic_name')->nullable()->after('cj_order_weight');
            $table->string('cj_tracking_url')->nullable()->after('cj_logistic_name');
            $table->string('cj_shipping_country_code', 10)->nullable()->after('cj_tracking_url');
            $table->string('cj_shipping_province')->nullable()->after('cj_shipping_country_code');
            $table->string('cj_shipping_city')->nullable()->after('cj_shipping_province');
            $table->string('cj_shipping_phone')->nullable()->after('cj_shipping_city');
            $table->text('cj_shipping_address')->nullable()->after('cj_shipping_phone');
            $table->string('cj_shipping_customer_name')->nullable()->after('cj_shipping_address');
            $table->string('cj_remark', 500)->nullable()->after('cj_shipping_customer_name');
            $table->string('cj_storage_id')->nullable()->after('cj_remark');
            $table->string('cj_storage_name')->nullable()->after('cj_storage_id');
            $table->timestamp('cj_created_at')->nullable()->after('cj_storage_name');
            $table->timestamp('cj_paid_at')->nullable()->after('cj_created_at');
            $table->timestamp('cj_store_created_at')->nullable()->after('cj_paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('linehaul_shipments', function (Blueprint $table) {
            $table->dropColumn([
                'cj_order_id',
                'cj_order_num',
                'cj_order_status',
                'cj_order_amount',
                'cj_product_amount',
                'cj_postage_amount',
                'cj_order_weight',
                'cj_logistic_name',
                'cj_tracking_url',
                'cj_shipping_country_code',
                'cj_shipping_province',
                'cj_shipping_city',
                'cj_shipping_phone',
                'cj_shipping_address',
                'cj_shipping_customer_name',
                'cj_remark',
                'cj_storage_id',
                'cj_storage_name',
                'cj_created_at',
                'cj_paid_at',
                'cj_store_created_at',
            ]);
        });
    }
};
