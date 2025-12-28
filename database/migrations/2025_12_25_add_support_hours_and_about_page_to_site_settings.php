<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            // Add support hours field for admin-managed support availability times
            $table->string('support_hours')->nullable()->after('support_phone')
                ->comment('Support availability times, e.g. "Mon-Sat, 9:00-18:00 GMT"');
            
            // Add about page HTML for admin-managed about page content
            $table->longText('about_page_html')->nullable()->after('customs_disclaimer')
                ->comment('Admin-managed HTML content for the About Us page');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['support_hours', 'about_page_html']);
        });
    }
};
