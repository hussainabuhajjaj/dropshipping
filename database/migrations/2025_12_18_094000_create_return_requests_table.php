<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('requested');
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_requests');
    }
};
