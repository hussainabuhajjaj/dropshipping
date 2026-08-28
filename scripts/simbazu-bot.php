#!/usr/bin/env php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$section = strtolower((string) ($argv[1] ?? 'summary'));

function tableExists(string $table): bool
{
    try {
        return Schema::hasTable($table);
    } catch (Throwable) {
        return false;
    }
}

function columnExists(string $table, string $column): bool
{
    try {
        return Schema::hasColumn($table, $column);
    } catch (Throwable) {
        return false;
    }
}

function money(float|int|string|null $value, string $currency = 'XOF'): string
{
    $amount = (float) ($value ?? 0);

    return number_format($amount, 0, '.', ',') . ' ' . $currency;
}

function countWhereToday(string $table, string $dateColumn = 'created_at'): int
{
    if (! tableExists($table) || ! columnExists($table, $dateColumn)) {
        return 0;
    }

    return (int) DB::table($table)->whereDate($dateColumn, now()->toDateString())->count();
}

function sumWhereToday(string $table, string $amountColumn, string $dateColumn = 'created_at'): float
{
    if (! tableExists($table) || ! columnExists($table, $amountColumn) || ! columnExists($table, $dateColumn)) {
        return 0.0;
    }

    return (float) DB::table($table)->whereDate($dateColumn, now()->toDateString())->sum($amountColumn);
}

function line(string $label, string|int|float|null $value): void
{
    echo $label . ': ' . ($value ?? '-') . PHP_EOL;
}

function salesReport(): void
{
    echo "Simbazu Sales\n";
    echo "Date: " . now()->format('Y-m-d H:i') . "\n\n";

    if (! tableExists('orders')) {
        echo "orders table not found\n";
        return;
    }

    $dateColumn = columnExists('orders', 'placed_at') ? 'placed_at' : 'created_at';
    $amountColumn = columnExists('orders', 'grand_total') ? 'grand_total' : 'total';

    $todayOrders = countWhereToday('orders', $dateColumn);
    $todayRevenue = sumWhereToday('orders', $amountColumn, $dateColumn);
    $paidToday = columnExists('orders', 'payment_status')
        ? (int) DB::table('orders')->whereDate($dateColumn, now()->toDateString())->where('payment_status', 'paid')->count()
        : 0;

    line('Orders today', $todayOrders);
    line('Paid orders today', $paidToday);
    line('Revenue today', money($todayRevenue));
    line('AOV today', $todayOrders > 0 ? money($todayRevenue / $todayOrders) : money(0));

    if (columnExists('orders', 'status')) {
        echo "\nOrder status:\n";
        DB::table('orders')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->each(fn ($row) => line((string) $row->status, (int) $row->total));
    }
}

function paymentsReport(): void
{
    echo "Simbazu Payments\n";
    echo "Date: " . now()->format('Y-m-d H:i') . "\n\n";

    if (! tableExists('payments')) {
        echo "payments table not found\n";
        return;
    }

    $failedToday = columnExists('payments', 'status')
        ? (int) DB::table('payments')->whereDate('created_at', now()->toDateString())->where('status', 'failed')->count()
        : 0;
    $pending = columnExists('payments', 'status')
        ? (int) DB::table('payments')->where('status', 'pending')->count()
        : 0;

    line('Failed today', $failedToday);
    line('Pending total', $pending);
    line('Paid today', columnExists('payments', 'status') ? (int) DB::table('payments')->whereDate('created_at', now()->toDateString())->where('status', 'paid')->count() : 0);
    line('Paid amount today', money(columnExists('payments', 'amount') ? (float) DB::table('payments')->whereDate('created_at', now()->toDateString())->where('status', 'paid')->sum('amount') : 0));

    if (columnExists('payments', 'provider')) {
        echo "\nBy provider/status:\n";
        DB::table('payments')
            ->select('provider', 'status', DB::raw('COUNT(*) as total'))
            ->groupBy('provider', 'status')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->each(fn ($row) => line($row->provider . ' ' . $row->status, (int) $row->total));
    }
}

function queueReport(): void
{
    echo "Simbazu Queues\n";
    echo "Date: " . now()->format('Y-m-d H:i') . "\n\n";

    line('Failed jobs', tableExists('failed_jobs') ? (int) DB::table('failed_jobs')->count() : 'failed_jobs table not found');

    if (tableExists('jobs')) {
        DB::table('jobs')
            ->select('queue', DB::raw('COUNT(*) as total'))
            ->groupBy('queue')
            ->orderByDesc('total')
            ->get()
            ->each(fn ($row) => line('Queued ' . $row->queue, (int) $row->total));
    } else {
        line('Queued jobs', 'jobs table not found');
    }
}

function wooReport(): void
{
    echo "Simbazu WooCommerce\n";
    echo "Date: " . now()->format('Y-m-d H:i') . "\n\n";

    line('Integration enabled', config('woocommerce.enabled') ? 'yes' : 'no');
    line('Queue', (string) config('woocommerce.queue', 'woocommerce'));
    line('Default currency', (string) config('woocommerce.currency', 'USD'));

    if (tableExists('woocommerce_product_maps')) {
        line('Product maps', (int) DB::table('woocommerce_product_maps')->count());
        line('Map errors', (int) DB::table('woocommerce_product_maps')->whereNotNull('last_error')->count());
    }

    if (tableExists('woocommerce_webhook_logs')) {
        line('Webhook failures', (int) DB::table('woocommerce_webhook_logs')->where('status', 'failed')->count());
        line('Webhooks today', countWhereToday('woocommerce_webhook_logs'));
    }

    if (tableExists('woocommerce_sync_logs')) {
        echo "\nRecent sync failures:\n";
        DB::table('woocommerce_sync_logs')
            ->where(function ($query) {
                $query->where('status', 'failed')->orWhereNotNull('error');
            })
            ->latest()
            ->limit(5)
            ->get(['entity_type', 'entity_id', 'action', 'error'])
            ->each(fn ($row) => line($row->entity_type . '#' . $row->entity_id . ' ' . $row->action, mb_strimwidth((string) $row->error, 0, 80)));
    }
}

function productsReport(): void
{
    echo "Simbazu Products\n";
    echo "Date: " . now()->format('Y-m-d H:i') . "\n\n";

    if (! tableExists('products')) {
        echo "products table not found\n";
        return;
    }

    line('Total products', (int) DB::table('products')->count());
    line('Active products', columnExists('products', 'status') ? (int) DB::table('products')->where('status', 'active')->count() : '-');
    line('Created today', countWhereToday('products'));
    line('Non-XOF products', columnExists('products', 'currency') ? (int) DB::table('products')->where('currency', '!=', 'XOF')->count() : '-');
    line('Missing source URL', columnExists('products', 'source_url') ? (int) DB::table('products')->whereNull('source_url')->count() : '-');
    line('Chinese titles', (int) DB::table('products')->where('name', 'REGEXP', '[一-龥]')->count());

    if (tableExists('product_variants')) {
        line('Variants', (int) DB::table('product_variants')->count());
    }
}

function campaignReport(): void
{
    echo "Simbazu Campaigns\n";
    echo "Date: " . now()->format('Y-m-d H:i') . "\n\n";

    if (! tableExists('storefront_campaigns')) {
        echo "storefront_campaigns table not found\n";
        return;
    }

    $campaigns = DB::table('storefront_campaigns')
        ->when(columnExists('storefront_campaigns', 'status'), fn ($query) => $query->where('status', 'active'))
        ->orderByDesc('id')
        ->limit(5)
        ->get();

    line('Active campaigns', $campaigns->count());

    foreach ($campaigns as $campaign) {
        $name = (string) ($campaign->name ?? $campaign->title ?? ('Campaign #' . $campaign->id));
        $dates = [];
        if (isset($campaign->starts_at)) {
            $dates[] = 'starts ' . $campaign->starts_at;
        }
        if (isset($campaign->ends_at)) {
            $dates[] = 'ends ' . $campaign->ends_at;
        }

        line('#' . $campaign->id . ' ' . $name, implode(', ', $dates) ?: 'active');
    }
}

match ($section) {
    'sales' => salesReport(),
    'payments', 'payment' => paymentsReport(),
    'queue', 'queues' => queueReport(),
    'woo', 'woocommerce' => wooReport(),
    'products', 'product' => productsReport(),
    'campaign', 'campaigns' => campaignReport(),
    default => (function (): void {
        salesReport();
        echo "\n";
        paymentsReport();
        echo "\n";
        queueReport();
        echo "\n";
        wooReport();
    })(),
};
