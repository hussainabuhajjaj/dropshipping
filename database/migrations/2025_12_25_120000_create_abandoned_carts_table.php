<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('abandoned_carts', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable()->index();
            $table->json('cart_data');
            $table->timestamp('abandoned_at')->nullable()->index();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamp('reminder_sent_at')->nullable()->index();
            $table->timestamp('recovered_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abandoned_carts');
    }
};
