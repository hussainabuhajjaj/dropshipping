<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->string('unsubscribe_token', 64)->nullable()->unique()->after('email');
            $table->timestamp('unsubscribed_at')->nullable()->after('unsubscribe_token');
        });
    }

    public function down(): void
    {
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->dropUnique(['unsubscribe_token']);
            $table->dropColumn(['unsubscribe_token', 'unsubscribed_at']);
        });
    }
};
