<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! $this->indexExists('products', 'idx_products_category_active')) {
            Schema::table('products', function (Blueprint $table) {
                $table->index(['category_id', 'is_active', 'deleted_at'], 'idx_products_category_active');
            });
        }
        
        if (! $this->indexExists('products', 'idx_products_id_active')) {
            Schema::table('products', function (Blueprint $table) {
                $table->index(['id', 'is_active'], 'idx_products_id_active');
            });
        }

        if (! $this->indexExists('products', 'idx_products_featured')) {
            Schema::table('products', function (Blueprint $table) {
                $table->index(['is_featured', 'is_active', 'updated_at'], 'idx_products_featured');
            });
        }

        if (! $this->indexExists('categories', 'idx_categories_active_parent')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->index(['is_active', 'parent_id', 'deleted_at'], 'idx_categories_active_parent');
            });
        }

        if (! $this->indexExists('categories', 'idx_categories_id_active')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->index(['id', 'is_active'], 'idx_categories_id_active');
            });
        }

        if (! $this->indexExists('categories', 'idx_categories_featured')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->index(['is_featured', 'is_active', 'updated_at'], 'idx_categories_featured');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->indexExists('products', 'idx_products_category_active')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex('idx_products_category_active');
            });
        }

        if ($this->indexExists('products', 'idx_products_id_active')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex('idx_products_id_active');
            });
        }

        if ($this->indexExists('products', 'idx_products_featured')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex('idx_products_featured');
            });
        }

        if ($this->indexExists('categories', 'idx_categories_active_parent')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropIndex('idx_categories_active_parent');
            });
        }

        if ($this->indexExists('categories', 'idx_categories_id_active')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropIndex('idx_categories_id_active');
            });
        }

        if ($this->indexExists('categories', 'idx_categories_featured')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropIndex('idx_categories_featured');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::selectOne('SHOW INDEX FROM `' . $table . '` WHERE Key_name = ?', [$indexName]);

        return $result !== null;
    }
};
