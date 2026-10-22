<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_margin_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('source')->default('manual');
            $table->string('event');
            $table->string('actor_type')->default('system');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->decimal('old_margin_percent', 8, 2)->nullable();
            $table->decimal('new_margin_percent', 8, 2)->nullable();
            $table->decimal('old_selling_price', 12, 2)->nullable();
            $table->decimal('new_selling_price', 12, 2)->nullable();
            $table->string('old_status')->nullable();
            $table->string('new_status')->nullable();
            $table->unsignedInteger('sales_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_margin_logs');
    }
};
