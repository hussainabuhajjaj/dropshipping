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
        Schema::table('return_requests', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('rejection_reason')->nullable()->after('approved_at');
            $table->string('return_label_url')->nullable()->after('rejection_reason');
            
            // Update status column to be enum with all possible statuses
            $table->string('status')->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['approved_by', 'approved_at', 'rejection_reason', 'return_label_url']);
        });
    }
};
