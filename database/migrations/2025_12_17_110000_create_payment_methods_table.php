<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('provider');
            $table->string('brand')->nullable();
            $table->string('last4', 4)->nullable();
            $table->unsignedTinyInteger('exp_month')->nullable();
            $table->unsignedSmallInteger('exp_year')->nullable();
            $table->string('nickname')->nullable();
            $table->boolean('is_default')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
