<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_content_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('target_type'); // campaign, banner, promotion, coupon, newsletter
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('locale')->default('fr');
            $table->string('channel')->nullable(); // web, email, push
            $table->json('generated_fields');
            $table->json('prompt_context')->nullable();
            $table->string('status')->default('draft'); // draft, pending_review, approved, rejected, published
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejected_reason')->nullable();
            $table->timestamps();
            $table->index(['target_type', 'target_id']);
            $table->index(['status', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_content_drafts');
    }
};
