<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fulfillment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fulfillment_provider_id')->nullable()->constrained('fulfillment_providers')->nullOnDelete();
            $table->foreignId('fulfillment_job_id')->nullable()->constrained('fulfillment_jobs')->nullOnDelete();
            $table->string('type');
            $table->string('status')->nullable();
            $table->string('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['order_item_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_events');
    }
};
