<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->longText('body_markdown');
            $table->string('action_url')->nullable();
            $table->string('action_label', 120)->nullable();
            $table->string('status', 40)->default('draft');
            $table->unsignedInteger('total_subscribers')->default(0);
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_campaigns');
    }
};
