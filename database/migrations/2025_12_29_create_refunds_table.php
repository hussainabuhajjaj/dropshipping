<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            
            // Relationships
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->onDelete('set null');
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->onDelete('set null');
            
            // Refund details
            $table->decimal('amount', 12, 2);
            $table->string('reason_code'); // e.g., 'customer_request', 'damaged', 'late_delivery', 'wrong_item', 'quality_issue'
            $table->text('admin_notes')->nullable();
            $table->text('customer_reason')->nullable();
            
            // Status tracking
            $table->enum('status', ['pending', 'approved', 'rejected', 'completed', 'cancelled'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            
            // Processing
            $table->string('transaction_id')->nullable()->unique(); // Payment gateway transaction ID
            $table->text('gateway_response')->nullable();
            $table->timestamp('processed_at')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['order_id', 'status']);
            $table->index(['shipment_id', 'status']);
            $table->index(['order_item_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
