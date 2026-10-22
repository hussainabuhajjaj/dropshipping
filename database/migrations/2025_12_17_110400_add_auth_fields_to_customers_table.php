<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->string('password')->nullable()->after('email_verified_at');
            $table->rememberToken()->after('password');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->unique('email');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->index('email');
            $table->dropColumn(['email_verified_at', 'password', 'remember_token']);
        });
    }
};
