<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('status')->nullable();
            $table->string('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');
    }
};
