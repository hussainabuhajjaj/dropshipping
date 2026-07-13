<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_segments', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->json('rules')->nullable()->comment('JSON: conditions for segment matching');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('storefront_campaigns', function (Blueprint $table): void {
            $table->json('segment_ids')->nullable()->after('newsletter_campaign_ids');
        });
    }

    public function down(): void
    {
        Schema::table('storefront_campaigns', function (Blueprint $table): void {
            $table->dropColumn('segment_ids');
        });

        Schema::dropIfExists('customer_segments');
    }
};
