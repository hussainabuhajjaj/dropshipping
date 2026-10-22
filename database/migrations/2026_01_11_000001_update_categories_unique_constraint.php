<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Remove old unique constraint on name
            $table->dropUnique(['name']);
            // Add parent_id column if not exists
            if (!Schema::hasColumn('categories', 'parent_id')) {
                $table->foreignId('parent_id')->nullable()->after('name')->constrained('categories')->nullOnDelete();
            }
            // Add new composite unique constraint
            $table->unique(['name', 'parent_id'], 'categories_name_parent_unique');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique('categories_name_parent_unique');
            // Restore old unique constraint on name
            $table->unique('name');
            // Optionally drop parent_id column if you want to revert
            // $table->dropForeign(['parent_id']);
            // $table->dropColumn('parent_id');
        });
    }
};
