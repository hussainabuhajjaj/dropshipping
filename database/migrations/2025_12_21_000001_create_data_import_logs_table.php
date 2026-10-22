<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_import_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 50);
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->json('summary')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_import_logs');
    }
};
