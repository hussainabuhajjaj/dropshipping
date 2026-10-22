<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });

        DB::table('categories')->whereNull('slug')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                DB::table('categories')
                    ->where('id', $row->id)
                    ->update(['slug' => Str::slug((string) $row->name)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
