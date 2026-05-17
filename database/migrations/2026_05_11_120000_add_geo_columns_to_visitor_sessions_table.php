<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitor_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('visitor_sessions', 'country_code')) {
                $table->string('country_code', 8)->nullable()->index()->after('ip_address');
            }

            if (! Schema::hasColumn('visitor_sessions', 'country_name')) {
                $table->string('country_name', 120)->nullable()->index()->after('country_code');
            }

            if (! Schema::hasColumn('visitor_sessions', 'region_name')) {
                $table->string('region_name', 120)->nullable()->after('country_name');
            }

            if (! Schema::hasColumn('visitor_sessions', 'city_name')) {
                $table->string('city_name', 120)->nullable()->index()->after('region_name');
            }

            if (! Schema::hasColumn('visitor_sessions', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('city_name');
            }

            if (! Schema::hasColumn('visitor_sessions', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
        });
    }

    public function down(): void
    {
        Schema::table('visitor_sessions', function (Blueprint $table): void {
            $columns = array_filter([
                Schema::hasColumn('visitor_sessions', 'country_code') ? 'country_code' : null,
                Schema::hasColumn('visitor_sessions', 'country_name') ? 'country_name' : null,
                Schema::hasColumn('visitor_sessions', 'region_name') ? 'region_name' : null,
                Schema::hasColumn('visitor_sessions', 'city_name') ? 'city_name' : null,
                Schema::hasColumn('visitor_sessions', 'latitude') ? 'latitude' : null,
                Schema::hasColumn('visitor_sessions', 'longitude') ? 'longitude' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
