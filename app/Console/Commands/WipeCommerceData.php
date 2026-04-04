<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WipeCommerceData extends Command
{
    protected $signature = 'commerce:wipe-data
        {--force : Execute the wipe without an interactive confirmation}
        {--keep-reviews : Keep product reviews}
        {--keep-returns : Keep return requests}';

    protected $description = 'Wipe order-driven commerce data and reset admin stats tables.';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This will permanently delete commerce data. Continue?')) {
            $this->components->info('Aborted.');

            return self::SUCCESS;
        }

        $tables = [
            'payment_events',
            'payment_webhooks',
            'refunds',
            'order_events',
            'order_audit_logs',
            'order_shippings',
            'tracking_events',
            'fulfillment_events',
            'fulfillments',
            'ali_express_sub_orders',
            'linehaul_shipments',
            'last_mile_deliveries',
            'affiliate_commissions',
            'payments',
            'order_items',
            'orders',
        ];

        if (! $this->option('keep-returns')) {
            array_unshift($tables, 'return_requests');
        }

        if (! $this->option('keep-reviews')) {
            array_unshift($tables, 'product_reviews');
        }

        $existingTables = array_values(array_filter($tables, fn (string $table): bool => Schema::hasTable($table)));

        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            foreach ($existingTables as $table) {
                DB::table($table)->truncate();
                $this->components->info("Truncated {$table}");
            }

            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        } catch (\Throwable $e) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('Commerce data wipe completed.');

        return self::SUCCESS;
    }
}
