<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Add supplier_currency field to preserve CJ currency
            $table->string('supplier_currency', 3)->default('USD')->after('currency');
            $table->index('supplier_currency');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            // Add supplier_currency field to variants
            $table->string('supplier_currency', 3)->default('USD')->after('currency');
            $table->index('supplier_currency');
        });

        // Migrate existing data: current currency becomes supplier_currency
        DB::table('products')->update([
            'supplier_currency' => DB::raw('currency')
        ]);

        DB::table('product_variants')->update([
            'supplier_currency' => DB::raw('currency')
        ]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['supplier_currency']);
            $table->dropColumn('supplier_currency');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex(['supplier_currency']);
            $table->dropColumn('supplier_currency');
        });
    }
};
