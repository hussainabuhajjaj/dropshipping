<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_inbox_messages', function (Blueprint $table) {
            $table->id();
            $table->string('platform')->index(); // instagram | messenger
            $table->string('channel')->index(); // comment | message
            $table->string('external_id')->nullable()->index();
            $table->string('sender_id')->index();
            $table->string('sender_handle')->nullable();
            $table->string('sender_name')->nullable();
            $table->text('text')->nullable();
            $table->string('media_type')->nullable();
            $table->string('media_url')->nullable();
            $table->string('recipient_id')->nullable();
            $table->string('parent_id')->nullable()->index(); // parent comment id
            $table->json('raw_payload')->nullable();
            $table->timestamp('received_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['platform', 'channel', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_inbox_messages');
    }
};
