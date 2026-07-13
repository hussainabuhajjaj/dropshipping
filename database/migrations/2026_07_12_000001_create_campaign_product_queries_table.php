<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_product_queries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storefront_campaign_id')->constrained()->cascadeOnDelete();
            $table->string('keywords')->nullable();
            $table->string('cj_category_id')->nullable();
            $table->decimal('min_price', 12, 2)->nullable();
            $table->decimal('max_price', 12, 2)->nullable();
            $table->unsignedInteger('max_products')->default(50);
            $table->unsignedInteger('margin_percent')->default(60);
            $table->boolean('auto_activate')->default(true);
            $table->string('sort_by')->nullable()->comment('newest, sales, price_asc, price_desc');
            $table->string('status')->default('pending')->comment('pending, sourcing, completed, failed');
            $table->text('error_message')->nullable();
            $table->timestamp('sourced_at')->nullable();
            $table->timestamps();
        });

        Schema::table('storefront_collections', function (Blueprint $table) {
            $table->boolean('is_campaign_auto_created')->default(false)->after('display_order');
            $table->foreignId('campaign_id')->nullable()->constrained('storefront_campaigns')->nullOnDelete()->after('is_campaign_auto_created');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('marketing_opt_in')->default(true)->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('marketing_opt_in');
        });
        Schema::table('storefront_collections', function (Blueprint $table) {
            $table->dropForeign(['campaign_id']);
            $table->dropColumn(['is_campaign_auto_created', 'campaign_id']);
        });
        Schema::dropIfExists('campaign_product_queries');
    }
};
