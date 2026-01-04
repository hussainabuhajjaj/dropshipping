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
        // Add policies tracking to orders table
        Schema::table('orders', function (Blueprint $table) {
            $table->string('policies_version')->nullable()->after('id')->comment('Version of policies accepted');
            $table->string('policies_hash')->nullable()->after('policies_version')->comment('SHA256 hash of policies accepted');
            $table->timestamp('policies_accepted_at')->nullable()->after('policies_hash')->comment('When policies were accepted');
        });

        // Chargeback cases table
        Schema::create('chargeback_cases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->comment('Linked order');
            $table->string('payment_reference')->comment('Payment gateway reference (stripe_id, etc)');
            $table->string('case_number')->unique()->comment('Chargeback case number from issuer');
            $table->enum('status', [
                'opened',           // Case just opened
                'awaiting_evidence', // Waiting for evidence submission
                'evidence_submitted', // Evidence submitted to issuer
                'under_review',     // Being reviewed by issuer
                'won',              // Case won
                'lost',             // Case lost
                'settled',          // Settlement reached
                'withdrawn'         // Chargeback withdrawn
            ])->default('opened')->index();
            $table->string('reason_code')->comment('Issuer reason code (e.g., 4855 for fraud)');
            $table->text('reason_description')->nullable()->comment('Human description of reason');
            $table->decimal('amount', 12, 2)->comment('Chargeback amount');
            $table->string('card_last_four')->nullable()->comment('Last 4 of card involved');
            $table->date('transaction_date')->nullable()->comment('Original transaction date');
            $table->date('chargeback_date')->nullable()->comment('When chargeback was initiated');
            $table->date('due_date')->nullable()->comment('Deadline for evidence submission');
            $table->text('customer_statement')->nullable()->comment('Customer\'s claim/statement');
            $table->text('merchant_response')->nullable()->comment('Our response to chargeback');
            $table->text('resolution_notes')->nullable()->comment('Notes on final resolution');
            $table->unsignedBigInteger('handled_by')->nullable()->comment('User who handled the case');
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('handled_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['status', 'due_date']);
            $table->index(['created_at']);
        });

        // Chargeback evidence table
        Schema::create('chargeback_evidence', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chargeback_case_id')->comment('Linked case');
            $table->enum('type', [
                'receipt',              // Order receipt/invoice
                'tracking',             // Tracking information
                'delivery_proof',       // Signature/delivery confirmation
                'communication',        // Email/customer communication
                'policy',               // Store policies
                'product_description',  // Product details at time of order
                'customer_consent',     // Customer consent/authorization
                'refund_response',      // Refund offer/response
                'dispute_response',     // Response to dispute
                'other'                 // Other evidence
            ])->index();
            $table->string('title')->comment('Evidence title/name');
            $table->text('description')->nullable()->comment('Description of evidence');
            $table->string('file_path')->nullable()->comment('Path to stored file (S3 or local)');
            $table->string('file_mime_type')->nullable()->comment('MIME type of file');
            $table->bigInteger('file_size')->nullable()->comment('File size in bytes');
            $table->text('content')->nullable()->comment('For text evidence, raw content');
            $table->text('url')->nullable()->comment('For reference evidence, URL');
            $table->timestamp('submitted_to_issuer_at')->nullable()->comment('When submitted to issuer');
            $table->unsignedBigInteger('uploaded_by')->nullable()->comment('User who uploaded');
            $table->timestamps();

            $table->foreign('chargeback_case_id')->references('id')->on('chargeback_cases')->cascadeOnDelete();
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['type', 'created_at']);
        });

        // Chargeback evidence bundles (for tracking exported/sent bundles)
        Schema::create('chargeback_evidence_bundles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chargeback_case_id')->comment('Associated case');
            $table->enum('format', ['text', 'pdf'])->comment('Bundle format');
            $table->string('file_path')->comment('Path to bundled file');
            $table->text('summary')->comment('Summary text included in bundle');
            $table->timestamp('submitted_to_issuer_at')->nullable()->comment('When sent to issuer');
            $table->unsignedBigInteger('created_by')->nullable()->comment('User who created bundle');
            $table->timestamps();

            $table->foreign('chargeback_case_id')->references('id')->on('chargeback_cases')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chargeback_evidence_bundles');
        Schema::dropIfExists('chargeback_evidence');
        Schema::dropIfExists('chargeback_cases');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['policies_version', 'policies_hash', 'policies_accepted_at']);
        });
    }
};
