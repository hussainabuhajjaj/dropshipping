<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->json('abandoned_cart_config')->nullable()->after('typesense_admin_key');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn('abandoned_cart_config');
        });
    }
};
