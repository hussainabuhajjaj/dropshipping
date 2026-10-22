<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_conversations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 24)->default('mobile');
            $table->string('status', 32)->default('open');
            $table->string('requested_agent', 16)->nullable();
            $table->string('active_agent', 16)->nullable();
            $table->boolean('ai_enabled')->default(true);
            $table->boolean('handoff_requested')->default(false);
            $table->string('priority', 16)->default('normal');
            $table->string('topic')->nullable();
            $table->json('tags')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_customer_message_at')->nullable();
            $table->timestamp('last_agent_message_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['status', 'last_message_at']);
            $table->index(['handoff_requested', 'status']);
            $table->index(['assigned_user_id', 'status']);
        });

        Schema::create('support_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('support_conversations')->cascadeOnDelete();
            $table->string('sender_type', 24);
            $table->foreignId('sender_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('message_type', 24)->default('text');
            $table->text('body');
            $table->json('metadata')->nullable();
            $table->boolean('is_internal_note')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
            $table->index(['sender_type', 'created_at']);
            $table->index(['is_internal_note', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_conversations');
    }
};

