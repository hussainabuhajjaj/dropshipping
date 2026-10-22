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
        Schema::create('tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->nullable()->unique();
            $table->string('status_code');
            $table->string('status_label')->nullable();
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->timestamp('occurred_at');
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index('occurred_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracking_events');
    }
};
