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
        Schema::create('fulfillment_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique(); // e.g. aliexpress, manual, null
            $table->string('type')->default('aliexpress');
            $table->string('driver_class'); // fully qualified strategy class
            $table->json('credentials')->nullable(); // provider-specific keys/urls/tokens
            $table->json('settings')->nullable(); // options like default shipping method
            $table->json('contact_info')->nullable(); // merged from other migration
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_blacklisted')->default(false);
            $table->unsignedTinyInteger('retry_limit')->default(3);
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('website_url')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fulfillment_providers');
    }
};
