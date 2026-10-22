<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cj_webhook_logs', function (Blueprint $table) {
            $table->string('request_id')->nullable()->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->boolean('processed')->default(false)->index();
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cj_webhook_logs', function (Blueprint $table) {
            $table->dropColumn(['request_id', 'attempts', 'processed', 'processed_at', 'last_error']);
        });
    }
};