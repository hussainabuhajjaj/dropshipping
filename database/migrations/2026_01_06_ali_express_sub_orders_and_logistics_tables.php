<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // AliExpress sub-orders
        Schema::create('ali_express_sub_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->string('ali_order_id')->nullable();
            $table->json('payload_snapshot');
            $table->timestamps();
        });

        // Linehaul shipments
        Schema::create('linehaul_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('tracking_number')->nullable();
            $table->decimal('total_weight_kg', 8, 3);
            $table->decimal('base_fee', 10, 2);
            $table->decimal('per_kg_rate', 10, 2);
            $table->decimal('total_fee', 10, 2);
            $table->json('shipment_snapshot')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamps();
        });

        // Last-mile deliveries
        Schema::create('last_mile_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('yango_reference')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('driver_phone')->nullable();
            $table->decimal('delivery_fee', 10, 2);
            $table->string('status')->default('pending');
            $table->timestamp('out_for_delivery_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        // Cart/order shipping quote snapshot
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('cart_total_weight_kg', 8, 3)->nullable();
            $table->decimal('linehaul_fee', 10, 2)->nullable();
            $table->decimal('last_mile_fee', 10, 2)->nullable();
            $table->json('shipping_quote_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['cart_total_weight_kg', 'linehaul_fee', 'last_mile_fee', 'shipping_quote_snapshot']);
        });
        Schema::dropIfExists('last_mile_deliveries');
        Schema::dropIfExists('linehaul_shipments');
        Schema::dropIfExists('ali_express_sub_orders');
    }
};
