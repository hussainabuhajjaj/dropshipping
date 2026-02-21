<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_audit_log', function (Blueprint $table): void {
            $table->id();
            $table->string('session_id', 64)->index();
            $table->timestamp('timestamp')->index();
            $table->string('operation_type', 50)->index();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->unsignedBigInteger('variant_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_type', 20)->index();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('source', 50)->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('validation_errors')->nullable();
            $table->json('business_rules_violated')->nullable();
            $table->json('profit_impact')->nullable();
            $table->json('compliance_flags')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['timestamp', 'operation_type']);
            $table->index(['user_id', 'timestamp']);
            $table->index(['product_id', 'timestamp']);
            $table->index(['session_id', 'timestamp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_audit_log');
    }
};
