<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('product_reviews')) {
            return;
        }

        Schema::table('product_reviews', function (Blueprint $table) {
            if (! Schema::hasColumn('product_reviews', 'status')) {
                $table->string('status')->default('pending')->after('body');
            }
            if (! Schema::hasColumn('product_reviews', 'images')) {
                $table->json('images')->nullable()->after('status');
            }
            if (! Schema::hasColumn('product_reviews', 'verified_purchase')) {
                $table->boolean('verified_purchase')->default(true)->after('images');
            }
            if (! Schema::hasColumn('product_reviews', 'helpful_count')) {
                $table->unsignedInteger('helpful_count')->default(0)->after('verified_purchase');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->dropColumn(['status', 'images', 'verified_purchase', 'helpful_count']);
        });
    }
};
