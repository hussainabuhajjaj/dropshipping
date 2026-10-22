<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'category_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->foreignId('category_id')->nullable()->after('name')->constrained('categories')->nullOnDelete();
            });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'category')) {
            $categories = DB::table('products')
                ->select('category')
                ->whereNotNull('category')
                ->distinct()
                ->pluck('category')
                ->filter()
                ->values();

            if ($categories->isNotEmpty()) {
                $now = now();
                $categoryIds = DB::table('categories')->pluck('id', 'name')->all();

                foreach ($categories as $name) {
                    if (isset($categoryIds[$name])) {
                        continue;
                    }

                    $id = DB::table('categories')->insertGetId([
                        'name' => $name,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $categoryIds[$name] = $id;
                }

                foreach ($categoryIds as $name => $id) {
                    DB::table('products')->where('category', $name)->update(['category_id' => $id]);
                }
            }

            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'category_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
                $table->dropColumn('category_id');
            });
        }

        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'category')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('category')->nullable()->after('name');
            });
        }

        if (Schema::hasTable('categories')) {
            Schema::drop('categories');
        }
    }
};
