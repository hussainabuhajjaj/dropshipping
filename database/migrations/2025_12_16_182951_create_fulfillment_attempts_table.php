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
        Schema::create('fulfillment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fulfillment_job_id')->constrained('fulfillment_jobs')->cascadeOnDelete();
            $table->unsignedInteger('attempt_no')->default(1);
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('status')->default('failed'); // success, failed
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fulfillment_attempts');
    }
};
