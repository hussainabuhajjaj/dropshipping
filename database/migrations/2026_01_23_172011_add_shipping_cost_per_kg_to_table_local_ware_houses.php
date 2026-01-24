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
        Schema::table('local_ware_houses', function (Blueprint $table) {
            $table->string('shipping_company_name')->nullable()->after('name');
            $table->string('shipping_method')->nullable()->after('shipping_company_name');
            $table->float('shipping_min_charge', 10, 2)->default(0)->after('shipping_method');
            $table->float('shipping_cost_per_kg', 10, 2)->default(0)->after('shipping_min_charge');
            $table->float('shipping_base_cost', 10, 2)->default(0)->after('shipping_cost_per_kg');
            $table->float('shipping_additional_cost', 10, 2)->default(0)->after('shipping_base_cost');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('local_ware_houses', function (Blueprint $table) {
            $table->dropColumn(['shipping_company_name','shipping_method','shipping_min_charge','shipping_cost_per_kg','shipping_additional_cost']);
        });
    }
};
