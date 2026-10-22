<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_announcements', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 5)->nullable();
            $table->boolean('enabled')->default(true);
            $table->string('title', 120);
            $table->text('body');
            $table->string('image')->nullable();
            $table->string('action_href', 500)->nullable();
            $table->boolean('send_database')->default(true);
            $table->boolean('send_push')->default(true);
            $table->boolean('send_email')->default(false);
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index(['enabled', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_announcements');
    }
};

