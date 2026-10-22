<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // No longer enforcing unique constraint; duplicate message_ids are handled by cleanup command
    }

    public function down(): void
    {
        // No rollback needed
    }
};