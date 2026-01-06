<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_audit_logs', function (Blueprint $table) {
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->string('actor')->nullable();
            $table->timestamp('changed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_audit_logs', function (Blueprint $table) {
            $table->dropColumn(['from_status', 'to_status', 'actor', 'changed_at']);
        });
    }
};
