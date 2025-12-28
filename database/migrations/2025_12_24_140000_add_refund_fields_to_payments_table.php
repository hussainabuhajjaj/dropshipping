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
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('refunded_amount', 12, 2)->default(0)->after('amount');
            $table->string('refund_status')->nullable()->after('status'); // null, partial, full
            $table->string('refund_reference')->nullable()->after('refund_status');
            $table->text('refund_reason')->nullable()->after('refund_reference');
            $table->foreignId('refunded_by')->nullable()->after('refund_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('refunded_at')->nullable()->after('refunded_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['refunded_by']);
            $table->dropColumn([
                'refunded_amount',
                'refund_status',
                'refund_reference',
                'refund_reason',
                'refunded_by',
                'refunded_at',
            ]);
        });
    }
};
