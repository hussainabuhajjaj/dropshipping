<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_reviews')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->rebuildSqlite();
            return;
        }

        Schema::table('product_reviews', function (Blueprint $table) {
            if (Schema::hasColumn('product_reviews', 'customer_id')) {
                $table->dropForeign(['customer_id']);
            }
            if (Schema::hasColumn('product_reviews', 'order_id')) {
                $table->dropForeign(['order_id']);
            }
            if (Schema::hasColumn('product_reviews', 'order_item_id')) {
                $table->dropForeign(['order_item_id']);
            }
        });

        // Laravel doesn't require doctrine/dbal in this repo; use SQL to alter nullability safely.
        DB::statement('ALTER TABLE product_reviews MODIFY customer_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE product_reviews MODIFY order_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE product_reviews MODIFY order_item_id BIGINT UNSIGNED NULL');

        Schema::table('product_reviews', function (Blueprint $table) {
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('order_item_id')->references('id')->on('order_items')->cascadeOnDelete();
        });

        Schema::table('product_reviews', function (Blueprint $table) {
            if (! Schema::hasColumn('product_reviews', 'external_provider')) {
                $table->string('external_provider')->nullable()->after('helpful_count');
            }
            if (! Schema::hasColumn('product_reviews', 'external_id')) {
                $table->string('external_id')->nullable()->after('external_provider');
            }
            if (! Schema::hasColumn('product_reviews', 'external_payload')) {
                $table->json('external_payload')->nullable()->after('external_id');
            }
        });

        // Composite unique supports idempotent external imports; multiple NULLs are allowed.
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->unique(['external_provider', 'external_id']);
            $table->index('external_provider');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_reviews')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->rebuildSqlite(dropExternal: true, forceNotNull: true);
            return;
        }

        Schema::table('product_reviews', function (Blueprint $table) {
            if (Schema::hasColumn('product_reviews', 'external_provider') && Schema::hasColumn('product_reviews', 'external_id')) {
                $table->dropUnique(['external_provider', 'external_id']);
            }
            if (Schema::hasColumn('product_reviews', 'external_provider')) {
                $table->dropIndex(['external_provider']);
            }
        });

        Schema::table('product_reviews', function (Blueprint $table) {
            if (Schema::hasColumn('product_reviews', 'external_payload')) {
                $table->dropColumn('external_payload');
            }
            if (Schema::hasColumn('product_reviews', 'external_id')) {
                $table->dropColumn('external_id');
            }
            if (Schema::hasColumn('product_reviews', 'external_provider')) {
                $table->dropColumn('external_provider');
            }
        });

        // Best-effort: revert nullability in non-sqlite environments.
        Schema::table('product_reviews', function (Blueprint $table) {
            if (Schema::hasColumn('product_reviews', 'customer_id')) {
                $table->dropForeign(['customer_id']);
            }
            if (Schema::hasColumn('product_reviews', 'order_id')) {
                $table->dropForeign(['order_id']);
            }
            if (Schema::hasColumn('product_reviews', 'order_item_id')) {
                $table->dropForeign(['order_item_id']);
            }
        });

        DB::statement('ALTER TABLE product_reviews MODIFY customer_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE product_reviews MODIFY order_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE product_reviews MODIFY order_item_id BIGINT UNSIGNED NOT NULL');

        Schema::table('product_reviews', function (Blueprint $table) {
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('order_item_id')->references('id')->on('order_items')->cascadeOnDelete();
        });
    }

    private function rebuildSqlite(bool $dropExternal = false, bool $forceNotNull = false): void
    {
        $pragma = DB::select('PRAGMA foreign_keys');
        $wasEnabled = false;
        if (is_array($pragma) && isset($pragma[0])) {
            $row = (array) $pragma[0];
            $wasEnabled = (bool) ($row['foreign_keys'] ?? false);
        }

        DB::statement('PRAGMA foreign_keys=OFF');

        Schema::create('product_reviews_new', function (Blueprint $table) use ($dropExternal, $forceNotNull) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $customer = $table->foreignId('customer_id');
            $order = $table->foreignId('order_id');
            $orderItem = $table->foreignId('order_item_id')->constrained('order_items');

            if (! $forceNotNull) {
                $customer->nullable();
                $order->nullable();
                $orderItem->nullable();
            }

            $customer->constrained()->cascadeOnDelete();
            $order->constrained()->cascadeOnDelete();
            $orderItem->cascadeOnDelete();

            $table->unsignedTinyInteger('rating');
            $table->string('title')->nullable();
            $table->text('body');
            $table->string('status')->default('pending');
            $table->json('images')->nullable();
            $table->boolean('verified_purchase')->default(true);
            $table->unsignedInteger('helpful_count')->default(0);

            if (! $dropExternal) {
                $table->string('external_provider')->nullable();
                $table->string('external_id')->nullable();
                $table->json('external_payload')->nullable();
                $table->unique(['external_provider', 'external_id']);
                $table->index('external_provider');
            }

            $table->timestamps();

            $table->unique('order_item_id');
            $table->index(['product_id', 'rating']);
        });

        $select = implode(', ', [
            'id',
            'product_id',
            'customer_id',
            'order_id',
            'order_item_id',
            'rating',
            'title',
            'body',
            "status",
            'images',
            'verified_purchase',
            'helpful_count',
            'created_at',
            'updated_at',
        ]);

        if (! $dropExternal) {
            $select .= ', NULL AS external_provider, NULL AS external_id, NULL AS external_payload';
        }

        $insertColumns = $dropExternal
            ? 'id, product_id, customer_id, order_id, order_item_id, rating, title, body, status, images, verified_purchase, helpful_count, created_at, updated_at'
            : 'id, product_id, customer_id, order_id, order_item_id, rating, title, body, status, images, verified_purchase, helpful_count, created_at, updated_at, external_provider, external_id, external_payload';

        DB::statement("INSERT INTO product_reviews_new ({$insertColumns}) SELECT {$select} FROM product_reviews");

        Schema::drop('product_reviews');
        Schema::rename('product_reviews_new', 'product_reviews');

        DB::statement('PRAGMA foreign_keys=' . ($wasEnabled ? 'ON' : 'OFF'));
    }
};
