<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        if (Schema::hasTable('products')) {
            try {
                DB::statement('ALTER TABLE products ADD FULLTEXT INDEX products_search_fulltext (name, description)');
            } catch (\Throwable) {
                // Index already exists or unsupported state; skip safely.
            }
        }

        if (Schema::hasTable('categories')) {
            try {
                DB::statement('ALTER TABLE categories ADD FULLTEXT INDEX categories_name_fulltext (name)');
            } catch (\Throwable) {
                // Index already exists or unsupported state; skip safely.
            }
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        if (Schema::hasTable('products')) {
            try {
                DB::statement('ALTER TABLE products DROP INDEX products_search_fulltext');
            } catch (\Throwable) {
                // Index missing; skip safely.
            }
        }

        if (Schema::hasTable('categories')) {
            try {
                DB::statement('ALTER TABLE categories DROP INDEX categories_name_fulltext');
            } catch (\Throwable) {
                // Index missing; skip safely.
            }
        }
    }
};
