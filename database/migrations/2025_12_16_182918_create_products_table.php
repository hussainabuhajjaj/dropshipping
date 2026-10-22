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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('default_fulfillment_provider_id')->nullable()->constrained('fulfillment_providers')->nullOnDelete();
            $table->string('status')->default('active'); // active, hidden, archived
            $table->string('currency', 3)->default('USD');
            $table->string('source_url')->nullable();
            $table->json('options')->nullable(); // variant options (size, color)
            $table->json('attributes')->nullable(); // custom attributes/snapshot
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
