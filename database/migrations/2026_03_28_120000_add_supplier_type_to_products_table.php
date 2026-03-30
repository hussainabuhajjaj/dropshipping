<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'supplier_type')) {
                $table->string('supplier_type', 32)->nullable()->after('supplier_id');
                $table->index('supplier_type');
            }
        });

        DB::table('products')
            ->whereNotNull('cj_pid')
            ->update(['supplier_type' => 'cj']);

        DB::table('products')
            ->whereNull('supplier_type')
            ->where(function ($query): void {
                $query->whereNotNull(DB::raw("json_unquote(json_extract(attributes, '$.ali_item_id'))"))
                    ->orWhereRaw("json_unquote(json_extract(attributes, '$.supplier_code')) in ('ae', 'aliexpress')");
            })
            ->update(['supplier_type' => 'aliexpress']);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'supplier_type')) {
                $table->dropIndex(['supplier_type']);
                $table->dropColumn('supplier_type');
            }
        });
    }
};
