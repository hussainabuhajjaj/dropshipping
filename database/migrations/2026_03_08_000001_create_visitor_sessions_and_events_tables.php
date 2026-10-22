<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitor_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('channel', 16)->index();
            $table->string('visitor_key', 100);
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id', 100)->nullable()->index();
            $table->string('locale', 12)->nullable()->index();
            $table->string('platform', 32)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['channel', 'visitor_key'], 'visitor_sessions_channel_visitor_unique');
        });

        Schema::create('visitor_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('visitor_session_id')->constrained('visitor_sessions')->cascadeOnDelete();
            $table->string('event_type', 40)->index();
            $table->string('route_name', 120)->nullable()->index();
            $table->string('path', 255)->index();
            $table->string('page_key', 255)->nullable()->index();
            $table->string('entity_type', 40)->nullable()->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->string('entity_slug', 191)->nullable()->index();
            $table->string('referrer', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id', 'occurred_at'], 'visitor_events_entity_lookup');
            $table->index(['page_key', 'occurred_at'], 'visitor_events_page_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_events');
        Schema::dropIfExists('visitor_sessions');
    }
};
