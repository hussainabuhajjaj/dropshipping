<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_translations', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 8)->index();
            $table->string('key');
            $table->text('value');
            $table->timestamps();

            $table->unique(['locale', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_translations');
    }
};
