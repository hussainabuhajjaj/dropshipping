<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_replies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id')->index();
            $table->string('status')->default('draft')->index(); // draft | auto | approved | sent | rejected | failed
            $table->text('draft_text')->nullable();
            $table->string('classification')->nullable()->index(); // product | pricing | shipping | order | greeting | complaint | spam | other
            $table->boolean('auto_send')->default(false);
            $table->string('external_reply_id')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->foreign('message_id')->references('id')->on('meta_inbox_messages')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_replies');
    }
};
