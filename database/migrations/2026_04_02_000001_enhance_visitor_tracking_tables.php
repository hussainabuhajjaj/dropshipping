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
            $table->string('source_type', 40)->nullable()->index()->after('platform');
            $table->string('source_host', 191)->nullable()->index()->after('source_type');
            $table->string('utm_source', 100)->nullable()->index()->after('source_host');
            $table->string('utm_medium', 100)->nullable()->index()->after('utm_source');
            $table->string('utm_campaign', 150)->nullable()->index()->after('utm_medium');
            $table->string('utm_term', 150)->nullable()->after('utm_campaign');
            $table->string('utm_content', 150)->nullable()->after('utm_term');
            $table->string('device_type', 24)->nullable()->index()->after('utm_content');
            $table->string('browser_family', 80)->nullable()->index()->after('device_type');
            $table->string('os_family', 80)->nullable()->index()->after('browser_family');
            $table->string('landing_route_name', 120)->nullable()->index()->after('user_agent');
            $table->string('landing_path', 255)->nullable()->index()->after('landing_route_name');
            $table->string('landing_page_key', 255)->nullable()->index()->after('landing_path');
            $table->string('last_route_name', 120)->nullable()->index()->after('landing_page_key');
            $table->string('last_path', 255)->nullable()->index()->after('last_route_name');
            $table->string('last_page_key', 255)->nullable()->index()->after('last_path');
            $table->unsignedInteger('hits_count')->default(0)->after('last_page_key');
        });

        Schema::table('visitor_events', function (Blueprint $table): void {
            $table->string('referrer_host', 191)->nullable()->index()->after('referrer');
        });
    }

    public function down(): void
    {
        Schema::table('visitor_events', function (Blueprint $table): void {
            $table->dropColumn('referrer_host');
        });

        Schema::table('visitor_sessions', function (Blueprint $table): void {
            $table->dropColumn([
                'source_type',
                'source_host',
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'utm_term',
                'utm_content',
                'device_type',
                'browser_family',
                'os_family',
                'landing_route_name',
                'landing_path',
                'landing_page_key',
                'last_route_name',
                'last_path',
                'last_page_key',
                'hits_count',
            ]);
        });
    }
};
