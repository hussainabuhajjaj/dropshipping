<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add exception fields to shipments table
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('exception_code')->nullable()->after('delivered_at');
            $table->text('exception_reason')->nullable()->after('exception_code');
            $table->timestamp('exception_at')->nullable()->after('exception_reason');
            $table->timestamp('resolved_at')->nullable()->after('exception_at');
            $table->text('admin_notes')->nullable()->after('resolved_at');
            $table->boolean('is_at_risk')->default(false)->after('admin_notes');

            // Indexes for querying
            $table->index(['exception_code', 'resolved_at']);
            $table->index('is_at_risk');
            $table->index('exception_at');
        });

        // Create shipment_exceptions table for audit trail
        Schema::create('shipment_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->onDelete('cascade');
            $table->string('exception_code');
            $table->text('exception_reason');
            $table->timestamp('occurred_at');
            $table->string('source'); // 'system', 'admin_manual', 'webhook'
            $table->text('raw_data')->nullable(); // JSON from webhook or system
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['shipment_id', 'occurred_at']);
            $table->index('exception_code');
        });

        // Create shipment_exception_resolutions table for resolution tracking
        Schema::create('shipment_exception_resolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->onDelete('cascade');
            $table->foreignId('exception_id')->constrained('shipment_exceptions')->onDelete('cascade');
            $table->string('resolution_code'); // 'customs_cleared', 'reshipped', 'refunded', 'investigating', etc.
            $table->text('admin_notes');
            $table->timestamp('resolved_at');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['shipment_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_exception_resolutions');
        Schema::dropIfExists('shipment_exceptions');

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex(['exception_code', 'resolved_at']);
            $table->dropIndex('is_at_risk');
            $table->dropIndex('exception_at');
            $table->dropColumn(['exception_code', 'exception_reason', 'exception_at', 'resolved_at', 'admin_notes', 'is_at_risk']);
        });
    }
};
