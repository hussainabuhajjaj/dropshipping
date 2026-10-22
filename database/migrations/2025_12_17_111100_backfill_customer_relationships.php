<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Use DB-agnostic UPDATE statements using subqueries so tests (sqlite) work
        DB::table('orders')
            ->whereNull('customer_id')
            ->update([
                'customer_id' => DB::raw('(SELECT id FROM customers WHERE customers.email = orders.email LIMIT 1)'),
            ]);

        DB::table('addresses')
            ->whereNull('customer_id')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('orders')
                    ->whereNotNull('orders.customer_id')
                    ->whereRaw('orders.shipping_address_id = addresses.id');
            })
            ->update([
                'customer_id' => DB::raw('(SELECT customer_id FROM orders WHERE orders.shipping_address_id = addresses.id LIMIT 1)'),
            ]);

        DB::table('addresses')
            ->whereNull('customer_id')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('orders')
                    ->whereNotNull('orders.customer_id')
                    ->whereRaw('orders.billing_address_id = addresses.id');
            })
            ->update([
                'customer_id' => DB::raw('(SELECT customer_id FROM orders WHERE orders.billing_address_id = addresses.id LIMIT 1)'),
            ]);
    }

    public function down(): void
    {
        // No-op for backfill migration.
    }
};
