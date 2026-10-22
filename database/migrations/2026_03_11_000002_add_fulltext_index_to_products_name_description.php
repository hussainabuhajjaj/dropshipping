<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->fullText(['name', 'description'], 'products_name_description_fulltext');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropFullText('products_name_description_fulltext');
        });
    }
};
