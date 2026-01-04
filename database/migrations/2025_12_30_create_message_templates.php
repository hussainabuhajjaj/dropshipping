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
        // Message templates table
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('Template identifier');
            $table->string('title')->comment('Display title for admin');
            $table->enum('type', [
                'delay',              // Shipment delay notification
                'customs',            // Customs clearance needed
                'split_shipments',    // Multiple shipments explanation
                'refund_update',      // Refund status update
                'delivery_update',    // Delivery status
                'exception',          // General exception
                'tracking',           // Tracking information
                'general'             // Generic template
            ])->index();
            $table->text('subject')->nullable()->comment('Email subject line');
            $table->text('message')->comment('Message template with {placeholders}');
            $table->text('description')->nullable()->comment('Admin notes about usage');
            $table->json('required_placeholders')->nullable()->comment('Placeholders required: ["order_number", "tracking_number"]');
            $table->json('available_placeholders')->nullable()->comment('All available placeholders for this template');
            $table->enum('default_channel', ['email', 'whatsapp', 'sms'])->default('email')->comment('Default send channel');
            $table->json('enabled_channels')->comment('Channels this template supports');
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('send_automatically')->default(false)->comment('Send on trigger automatically');
            $table->json('trigger_types')->nullable()->comment('Auto-send triggers: ["shipment_delayed", "customs_hold"]');
            $table->integer('auto_send_delay_hours')->nullable()->comment('Delay before auto-send (hours)');
            $table->text('condition_rules')->nullable()->comment('JSON conditions to match before sending');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        // Message logs table
        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_template_id')->comment('Template used');
            $table->unsignedBigInteger('order_id')->comment('Associated order');
            $table->unsignedBigInteger('shipment_id')->nullable()->comment('Associated shipment (if applicable)');
            $table->unsignedBigInteger('customer_id')->nullable()->comment('Recipient customer');
            $table->string('recipient')->comment('Email, phone, or ID');
            $table->enum('channel', ['email', 'whatsapp', 'sms', 'manual'])->comment('How message was sent');
            $table->text('subject')->nullable()->comment('Email subject used');
            $table->text('message_content')->comment('Actual message sent (with placeholders filled)');
            $table->json('placeholders_used')->nullable()->comment('Placeholder values used');
            $table->enum('status', [
                'queued',      // Waiting to send
                'sending',     // In progress
                'sent',        // Successfully sent
                'bounced',     // Failed delivery
                'failed',      // Send failed
                'opened',      // Opened (email)
                'clicked'      // Link clicked (email)
            ])->default('sent')->index();
            $table->text('error_message')->nullable()->comment('Error if failed');
            $table->string('external_message_id')->nullable()->comment('Provider message ID');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->unsignedBigInteger('sent_by')->nullable()->comment('Admin who sent manually');
            $table->boolean('is_automatic')->default(true)->comment('Auto-sent or manual');
            $table->timestamps();

            $table->foreign('message_template_id')->references('id')->on('message_templates')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('shipment_id')->references('id')->on('shipments')->nullOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('sent_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['order_id', 'channel']);
            $table->index(['customer_id', 'sent_at']);
            $table->index(['status', 'sent_at']);
        });

        // Message trigger history (for scheduling auto-sends)
        Schema::create('message_trigger_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_template_id')->comment('Template to send');
            $table->unsignedBigInteger('order_id')->comment('Trigger context order');
            $table->unsignedBigInteger('shipment_id')->nullable()->comment('Trigger context shipment');
            $table->string('trigger_type')->comment('What triggered: shipment_delayed, customs_hold, etc');
            $table->json('trigger_data')->nullable()->comment('Data from trigger event');
            $table->enum('status', ['pending', 'scheduled', 'sent', 'cancelled', 'failed'])->default('pending')->index();
            $table->timestamp('scheduled_for')->nullable()->comment('When to send');
            $table->timestamp('sent_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->unsignedBigInteger('message_log_id')->nullable()->comment('Resulting message log');
            $table->timestamps();

            $table->foreign('message_template_id')->references('id')->on('message_templates')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('shipment_id')->references('id')->on('shipments')->nullOnDelete();
            $table->foreign('message_log_id')->references('id')->on('message_logs')->nullOnDelete();
            $table->index(['trigger_type', 'status']);
            $table->index(['scheduled_for']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_trigger_history');
        Schema::dropIfExists('message_logs');
        Schema::dropIfExists('message_templates');
    }
};
