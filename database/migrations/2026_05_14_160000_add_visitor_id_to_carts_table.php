<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            if (! Schema::hasColumn('carts', 'visitor_id')) {
                $table->string('visitor_id')->nullable()->after('session_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            if (Schema::hasColumn('carts', 'visitor_id')) {
                $table->dropIndex(['visitor_id']);
                $table->dropColumn('visitor_id');
            }
        });
    }
};
