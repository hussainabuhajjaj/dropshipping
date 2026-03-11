<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_logs', function (Blueprint $table) {
            $table->id();
            $table->string('query', 255);
            $table->string('type', 20)->default('search'); // search, suggest
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->integer('results_count')->default(0);
            $table->float('execution_time_ms')->nullable();
            $table->boolean('cached')->default(false);
            $table->json('search_params')->nullable(); // Store filters, sort, etc.
            $table->timestamps();

            // Indexes for performance
            $table->index(['query', 'created_at']);
            $table->index(['type', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('ip_address');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_logs');
    }
};
