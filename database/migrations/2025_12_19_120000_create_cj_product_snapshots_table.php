<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cj_product_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('pid')->unique();
            $table->string('name')->nullable();
            $table->string('sku')->nullable()->index();
            $table->string('category_id')->nullable()->index();
            $table->json('payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cj_product_snapshots');
    }
};
