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

        if (! Schema::hasColumn('products', 'searchable_text')) {
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::statement('ALTER TABLE products ADD COLUMN searchable_text TEXT NULL AFTER description');
            } else {
                Schema::table('products', function ($table): void {
                    $table->text('searchable_text')->nullable();
                });
            }
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            try {
                DB::statement('ALTER TABLE products ADD FULLTEXT INDEX products_search_v2_fulltext (name, description, code, meta_title, searchable_text)');
            } catch (\Throwable) {
                // Index already exists; skip safely.
            }
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            try {
                DB::statement('ALTER TABLE products DROP INDEX products_search_v2_fulltext');
            } catch (\Throwable) {
                // Index missing; skip safely.
            }
        }

        if (Schema::hasColumn('products', 'searchable_text')) {
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::statement('ALTER TABLE products DROP COLUMN searchable_text');
            } else {
                Schema::table('products', function ($table): void {
                    $table->dropColumn('searchable_text');
                });
            }
        }
    }
};
