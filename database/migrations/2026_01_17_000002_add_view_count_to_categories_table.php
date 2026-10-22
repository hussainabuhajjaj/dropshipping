<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'view_count')) {
                $table->unsignedBigInteger('view_count')->default(0)->after('is_active');
                $table->index('view_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'view_count')) {
                $table->dropIndex(['view_count']);
                $table->dropColumn('view_count');
            }
        });
    }
};
