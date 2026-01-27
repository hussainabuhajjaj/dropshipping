<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'ali_category_id')) {
                $table->string('ali_category_id')->nullable()->unique()->after('cj_payload');
            }
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'ali_category_id')) {
                $table->dropUnique(['ali_category_id']);
                $table->dropColumn('ali_category_id');
            }
        });
    }
};
