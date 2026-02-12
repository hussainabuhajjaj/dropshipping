<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'cj_removed_from_shelves_at')) {
                $table->timestamp('cj_removed_from_shelves_at')->nullable()->after('cj_synced_at');
            }

            if (! Schema::hasColumn('products', 'cj_removed_reason')) {
                $table->string('cj_removed_reason', 500)->nullable()->after('cj_removed_from_shelves_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'cj_removed_reason')) {
                $table->dropColumn('cj_removed_reason');
            }

            if (Schema::hasColumn('products', 'cj_removed_from_shelves_at')) {
                $table->dropColumn('cj_removed_from_shelves_at');
            }
        });
    }
};

