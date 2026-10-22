<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            if (Schema::hasColumn('product_reviews', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
