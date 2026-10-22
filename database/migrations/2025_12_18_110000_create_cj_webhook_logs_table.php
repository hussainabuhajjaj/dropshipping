<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cj_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->nullable()->index();
            $table->string('type')->nullable()->index();
            $table->string('message_type')->nullable()->index();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cj_webhook_logs');
    }
};
