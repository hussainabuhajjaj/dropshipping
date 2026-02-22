<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliates', function (Blueprint $table) {
            if (! Schema::hasColumn('affiliates', 'name')) {
                $table->string('name')->after('id');
            }
            if (! Schema::hasColumn('affiliates', 'email')) {
                $table->string('email')->unique()->after('name');
            }
            if (! Schema::hasColumn('affiliates', 'password')) {
                $table->string('password')->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('affiliates', function (Blueprint $table) {
            if (Schema::hasColumn('affiliates', 'password')) {
                $table->dropColumn('password');
            }
            if (Schema::hasColumn('affiliates', 'email')) {
                $table->dropColumn('email');
            }
            if (Schema::hasColumn('affiliates', 'name')) {
                $table->dropColumn('name');
            }
        });
    }
};
