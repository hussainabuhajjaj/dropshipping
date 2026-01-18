<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_campaign_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newsletter_campaign_id')
                ->constrained('newsletter_campaigns')
                ->cascadeOnDelete();
            $table->foreignId('newsletter_subscriber_id')
                ->constrained('newsletter_subscribers')
                ->cascadeOnDelete();
            $table->string('email');
            $table->string('status', 40)->default('queued');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['newsletter_campaign_id', 'status']);
            $table->unique(['newsletter_campaign_id', 'newsletter_subscriber_id'], 'campaign_subscriber_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_campaign_logs');
    }
};
