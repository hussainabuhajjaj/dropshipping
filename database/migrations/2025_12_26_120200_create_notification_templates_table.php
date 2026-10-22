<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('channel', ['email', 'sms', 'both'])->default('email');
            $table->boolean('is_enabled')->default(true);
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('sender_name')->nullable();
            $table->string('sender_email')->nullable();
            $table->json('variables')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
