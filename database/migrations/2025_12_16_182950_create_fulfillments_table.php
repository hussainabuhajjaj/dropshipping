<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fulfillment_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fulfillment_provider_id')->constrained('fulfillment_providers')->cascadeOnDelete();
            $table->json('payload')->nullable(); // request payload snapshot
            $table->string('status')->default('pending'); // pending, in_progress, succeeded, failed, needs_action
            $table->string('external_reference')->nullable(); // supplier-side order id
            $table->text('last_error')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
            $table->index(['fulfillment_provider_id', 'status'], 'fulfillment_jobs_provider_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fulfillment_jobs');
    }
};
