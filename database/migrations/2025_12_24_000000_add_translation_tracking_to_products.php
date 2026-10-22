<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('translation_status')->default('pending')->comment('pending, in_progress, completed, failed');
            $table->json('translated_locales')->nullable()->comment('Array of successfully translated locales');
            $table->timestamp('last_translation_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['translation_status', 'translated_locales', 'last_translation_at']);
        });
    }
};
