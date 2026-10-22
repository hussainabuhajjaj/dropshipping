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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone', 30)->nullable()->index();
            $table->string('country_code', 2)->default('CI');
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('postal_code')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
