<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->date('posted_date')->index();
            $table->unsignedTinyInteger('day_rank')->comment('1, 2, or 3 for the day');
            $table->string('category_slug', 80)->nullable();
            $table->string('image_url', 1024)->nullable();
            $table->text('caption')->nullable();
            $table->text('hashtags')->nullable();
            $table->unsignedSmallInteger('quality_score')->default(0);
            $table->timestamps();

            $table->unique(['posted_date', 'day_rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_posts');
    }
};