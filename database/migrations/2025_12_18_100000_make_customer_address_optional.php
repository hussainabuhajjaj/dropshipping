<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ALTER TABLE ... MODIFY is MySQL-specific and will fail on sqlite used by tests.
        // Skip modification for sqlite; it's acceptable for tests since schema is ephemeral.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE customers MODIFY address_line1 VARCHAR(255) NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE customers MODIFY address_line1 VARCHAR(255) NOT NULL');
    }
};
