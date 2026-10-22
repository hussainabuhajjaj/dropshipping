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
        Schema::table('products', function (Blueprint $table) {
            // Track when product was imported from CJ
            $table->timestamp('cj_imported_at')->nullable()->after('cj_synced_at');
            
            // Track the import batch/session
            $table->string('cj_import_batch_id')->nullable()->after('cj_imported_at');
            
            // Add index for filtering newly imported products
            $table->index(['cj_imported_at']);
            $table->index(['cj_import_batch_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['cj_imported_at']);
            $table->dropIndex(['cj_import_batch_id']);
            $table->dropColumn(['cj_imported_at', 'cj_import_batch_id']);
        });
    }
};
