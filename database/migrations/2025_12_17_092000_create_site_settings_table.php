<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('default_fulfillment_provider_id')
                ->nullable()
                ->constrained('fulfillment_providers')
                ->nullOnDelete();
            $table->string('support_email')->nullable();
            $table->string('support_whatsapp')->nullable();
            $table->string('support_phone')->nullable();
            $table->string('delivery_window')->nullable();
            $table->text('shipping_message')->nullable();
            $table->text('customs_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
