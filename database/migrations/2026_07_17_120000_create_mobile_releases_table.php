<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_releases', function (Blueprint $table) {
            $table->id();
            $table->string('version', 50);
            $table->string('platform', 20)->default('android');
            $table->string('file_path');
            $table->string('disk', 20)->default('public');
            $table->string('original_name')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->text('release_notes')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['platform', 'is_active']);
            $table->index(['platform', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_releases');
    }
};
