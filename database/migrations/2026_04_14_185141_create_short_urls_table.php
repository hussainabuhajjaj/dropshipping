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
        Schema::create('short_urls', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->text('original_url');
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('cascade');
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();
            
            $table->index('code');
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('short_urls');
    }
};
