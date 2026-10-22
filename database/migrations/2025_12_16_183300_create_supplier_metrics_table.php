<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fulfillment_provider_id')->unique()->constrained('fulfillment_providers')->cascadeOnDelete();
            $table->unsignedInteger('fulfilled_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('refunded_count')->default(0);
            $table->decimal('average_lead_time_days', 8, 2)->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_metrics');
    }
};
