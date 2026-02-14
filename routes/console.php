<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use App\Models\ProductReview;
use Illuminate\Support\Facades\Schedule;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Domain\Products\Services\CjProductImportService;
use App\Domain\Products\Services\CjCategorySyncService;
use App\Domain\Products\Models\Category;
use App\Domain\Products\Models\Product;
use App\Models\SiteSetting;
use App\Jobs\PollCJFulfillmentStatus;
use App\Domain\Fulfillment\Models\FulfillmentJob;
//
//Artisan::command('inspire', function () {
//    $this->comment(Inspiring::quote());
//})->purpose('Display an inspiring quote');

Artisan::command('data:cleanup-customers {--dry-run} {--force}', function () {
    $dryRun = (bool) $this->option('dry-run');
    $force = (bool) $this->option('force');

    if (app()->isProduction()) {
        $this->error('This command is blocked in production.');
        return 1;
    }

    if (! $dryRun && ! $force) {
        $this->error('Refusing to run destructive cleanup without --force.');
        $this->line('Use --dry-run to preview, then rerun with --force to apply changes.');
        return 1;
    }

    $this->info($dryRun ? 'Running in dry-run mode.' : 'Running cleanup.');

    $duplicates = DB::table('customers')
        ->select('email', DB::raw('COUNT(*) as count'))
        ->whereNotNull('email')
        ->groupBy('email')
        ->having('count', '>', 1)
        ->get();

    foreach ($duplicates as $dup) {
        $ids = DB::table('customers')->where('email', $dup->email)->orderBy('id')->pluck('id')->all();
        $keepId = array_shift($ids);

        if (! $dryRun) {
            DB::table('orders')->whereIn('customer_id', $ids)->update(['customer_id' => $keepId]);
            DB::table('addresses')->whereIn('customer_id', $ids)->update(['customer_id' => $keepId]);
            DB::table('payment_methods')->whereIn('customer_id', $ids)->update(['customer_id' => $keepId]);
            DB::table('gift_cards')->whereIn('customer_id', $ids)->update(['customer_id' => $keepId]);
            DB::table('coupon_redemptions')->whereIn('customer_id', $ids)->update(['customer_id' => $keepId]);
            DB::table('customers')->whereIn('id', $ids)->delete();
        }

        $this->line("Merged duplicates for {$dup->email}: kept {$keepId}, removed " . implode(',', $ids));
    }

    $orders = DB::table('orders')
        ->whereNull('customer_id')
        ->whereNotNull('email')
        ->get();

    foreach ($orders as $order) {
        $customer = DB::table('customers')->where('email', $order->email)->first();

        if (! $customer && ! $dryRun) {
            $shipping = $order->shipping_address_id
                ? DB::table('addresses')->where('id', $order->shipping_address_id)->first()
                : null;

            $name = $shipping?->name ?: $order->email;
            $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
            $first = array_shift($parts) ?: $order->email;
            $last = $parts ? implode(' ', $parts) : null;

            $customerId = DB::table('customers')->insertGetId([
                'first_name' => $first,
                'last_name' => $last,
                'email' => $order->email,
                'phone' => $shipping?->phone,
                'country_code' => $shipping?->country,
                'city' => $shipping?->city,
                'region' => $shipping?->state,
                'address_line1' => $shipping?->line1,
                'address_line2' => $shipping?->line2,
                'postal_code' => $shipping?->postal_code,
                'metadata' => json_encode(['source' => 'cleanup']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $customerId = $customer?->id;
        }

        if ($customerId && ! $dryRun) {
            DB::table('orders')->where('id', $order->id)->update(['customer_id' => $customerId]);

            DB::table('addresses')
                ->whereIn('id', array_filter([$order->shipping_address_id, $order->billing_address_id]))
                ->whereNull('customer_id')
                ->update(['customer_id' => $customerId]);
        }
    }

    $this->info('Cleanup complete.');
})->purpose('Deduplicate customers and backfill customer relationships (requires --force outside dry-run, blocked in production).');

Artisan::command('reviews:auto-approve', function () {
    $settings = SiteSetting::query()->first();
    $days = (int) ($settings?->auto_approve_review_days ?? 0);

    if ($days <= 0) {
        $this->info('Auto-approve is disabled.');
        return;
    }

    $cutoff = now()->subDays($days);
    $count = ProductReview::query()
        ->where('status', 'pending')
        ->where('created_at', '<=', $cutoff)
        ->update(['status' => 'approved']);

    $this->info("Approved {$count} review(s).");
})->purpose('Approve pending reviews older than the configured number of days.');

Schedule::command('cj:refresh-token')
    ->hourly()
    ->withoutOverlapping(50);

Schedule::command('cj:sync-linehaul-orders --page=1 --page-size=50')
    ->everyFifteenMinutes()
    ->withoutOverlapping(14)
    ->runInBackground();

Schedule::command('cj:sync-categories')
    ->dailyAt('02:00')
    ->withoutOverlapping(120);

Schedule::command('cj:sync-products-v2 --chunk=25')
    ->everySixHours()
    ->withoutOverlapping(300)
    ->runInBackground();

Schedule::command('cj:sync-variants')
    ->everyTwoHours()
    ->withoutOverlapping(110);

Schedule::command('cj:fix-product-details --limit=500 --with-variants --with-media --with-reviews')
    ->dailyAt('03:00')
    ->withoutOverlapping(240)
    ->runInBackground();

Schedule::command('cj:webhooks-cleanup')->dailyAt('03:15');
Schedule::command('categories:translate --locales=en,fr --source=en --queue')->dailyAt('01:30');
Schedule::command('products:translate --locales=en,fr --source=en --queue')->dailyAt('03:40');
Schedule::command('reviews:auto-approve')->dailyAt('04:30');
Schedule::command('mobile:translations:translate --from=en --to=fr --limit=500')->weeklyOn(0, '03:00');

if (filter_var(env('PRODUCT_CATEGORY_REPRICING_ENABLED', false), FILTER_VALIDATE_BOOL)) {
    Schedule::command('products:reprice-by-category-tiers --chunk=500')
        ->dailyAt((string) env('PRODUCT_CATEGORY_REPRICING_AT', '04:45'))
        ->withoutOverlapping(240)
        ->runInBackground();
}

if (filter_var(env('PRODUCT_AUTO_HIDE_STALE_ENABLED', false), FILTER_VALIDATE_BOOL)) {
    $staleHours = max(1, (int) env('PRODUCT_STALE_SYNC_HOURS', 48));

    Schedule::command("products:auto-hide-stale-cj --stale-hours={$staleHours} --chunk=500")
        ->hourly()
        ->withoutOverlapping(55)
        ->runInBackground();
}

// Optional scheduled queue worker for environments without Supervisor/systemd.
// Enable with: SCHEDULED_QUEUE_WORKER_ENABLED=true
if (filter_var(env('SCHEDULED_QUEUE_WORKER_ENABLED', false), FILTER_VALIDATE_BOOL)) {
    $scheduledQueues = env('SCHEDULED_QUEUE_WORKER_QUEUES', 'default,import,variants,media,translations,seo,pricing,support');

    Schedule::command("queue:work --once --queue={$scheduledQueues} --tries=3 --timeout=120 --sleep=0")
        ->everyTenSeconds()
        ->withoutOverlapping();
}

if (filter_var(env('QUEUE_REPORTING_ENABLED', false), FILTER_VALIDATE_BOOL)) {
    Schedule::command('queue:report-email')
        ->everyTenMinutes()
        ->withoutOverlapping()
        ->runInBackground();
}

if (filter_var(env('SUPPORT_CHAT_ESCALATION_ENABLED', true), FILTER_VALIDATE_BOOL)) {
    Schedule::command('support:escalate-pending')
        ->everyFiveMinutes()
        ->withoutOverlapping(4)
        ->runInBackground();
}

if (filter_var(env('SUPPORT_CHAT_DIGEST_ENABLED', true), FILTER_VALIDATE_BOOL)) {
    Schedule::command('support:escalation-digest')
        ->everyThirtyMinutes()
        ->withoutOverlapping(25)
        ->runInBackground();
}

Artisan::command('cj:token', function () {
    $client = app(CJDropshippingClient::class);
    $token = $client->getAccessToken(true);
    $this->info('CJ access token: ' . $token);
})->purpose('Fetch and cache a CJ Dropshipping access token');

Artisan::command('cj:settings', function () {
    $client = app(CJDropshippingClient::class);
    $resp = $client->getSettings();
    $this->info('CJ settings:');
    $this->line(json_encode($resp->data, JSON_PRETTY_PRINT));
})->purpose('Fetch CJ account settings');

Artisan::command('cj:logout', function () {
    $client = app(CJDropshippingClient::class);
    $resp = $client->logout();
    $this->info($resp->ok ? 'CJ tokens cleared and logout requested.' : 'Logout call failed.');
})->purpose('Logout CJ access token and clear cache');

Artisan::command('cj:set-account {--name=} {--email=}', function () {
    $name = $this->option('name');
    $email = $this->option('email');

    if (! $name && ! $email) {
        $this->error('Provide --name and/or --email');
        return;
    }

    $client = app(CJDropshippingClient::class);
    $resp = $client->updateAccount($name, $email);
    $this->info('CJ account updated: ' . ($resp->message ?? 'OK'));
    $this->line(json_encode($resp->data, JSON_PRETTY_PRINT));
})->purpose('Update CJ account openName/openEmail');

Artisan::command('cj:product {pid}', function (string $pid) {
    $client = app(CJDropshippingClient::class);
    $resp = $client->getProduct($pid);
    $this->info('Product:');
    $this->line(json_encode($resp->data, JSON_PRETTY_PRINT));
})->purpose('Fetch CJ product details by pid');

Artisan::command('cj:variants {pid}', function (string $pid) {
    $client = app(CJDropshippingClient::class);
    $resp = $client->getVariantsByPid($pid);
    $this->info('Variants:');
    $this->line(json_encode($resp->data, JSON_PRETTY_PRINT));
})->purpose('Fetch CJ variants by pid');

Artisan::command('cj:variant-stock {vid}', function (string $vid) {
    $client = app(CJDropshippingClient::class);
    $resp = $client->getStockByVid($vid);
    $this->info('Stock:');
    $this->line(json_encode($resp->data, JSON_PRETTY_PRINT));
})->purpose('Fetch CJ stock by variant vid');

Artisan::command('cj:sync-products {--start-page=1} {--pages=1} {--page-size=24} {--queue}', function () {
    $start = (int) $this->option('start-page');
    $pages = (int) $this->option('pages');
    $pageSize = (int) $this->option('page-size');
    $queue = (bool) $this->option('queue');

    for ($i = 0; $i < $pages; $i++) {
        $page = $start + $i;
        $job = new \App\Jobs\SyncCjProductsJob($page, $pageSize);

        if ($queue) {
            dispatch($job);
            $this->info("Queued CJ sync for page {$page}");
        } else {
            dispatch_sync($job);
            $this->info("Synced CJ page {$page}");
        }
    }
})->purpose('Sync CJ products into local snapshots');


Artisan::command('cj:sync-my-products-job {--start-page=1} {--page-size=24} {--max-pages=50}', function () {
    $start = (int) $this->option('start-page');
    $pageSize = (int) $this->option('page-size');
    $maxPages = (int) $this->option('max-pages');

    for ($page = $start; $page < $start + $maxPages; $page++) {
        \App\Jobs\SyncCjMyProductsJob::dispatch($page, $pageSize);
        $this->info("Dispatched SyncCjMyProductsJob for page {$page} (size {$pageSize})");
    }
    $this->info('All jobs dispatched. Monitor logs for progress.');
})->purpose('Queue CJ My Products import jobs (skips already imported products)');


Artisan::command('categories:dedupe {--dry-run}', function () {
    $dryRun = (bool) $this->option('dry-run');
    $groups = Category::select('name', 'parent_id', DB::raw('COUNT(*) as dupes'))
        ->groupBy('name', 'parent_id')
        ->having('dupes', '>', 1)
        ->get();

    if ($groups->isEmpty()) {
        $this->info('No duplicate categories found.');
        return;
    }

    $totalDeleted = 0;
    $totalGroups = $groups->count();

    foreach ($groups as $group) {
        $candidates = Category::where('name', $group->name)
            ->where('parent_id', $group->parent_id)
            ->orderByRaw('cj_id IS NULL') // prefer records with cj_id
            ->orderBy('id')
            ->get();

        if ($candidates->count() < 2) {
            continue;
        }

        $keep = $candidates->first();
        $dupes = $candidates->slice(1);

        foreach ($dupes as $dup) {
            $productsCount = Product::where('category_id', $dup->id)->count();
            $childrenCount = Category::where('parent_id', $dup->id)->count();

            $this->info(sprintf(
                'Merging "%s" (dup #%d) into #%d: products %d, children %d',
                $group->name,
                $dup->id,
                $keep->id,
                $productsCount,
                $childrenCount,
            ));

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($dup, $keep) {
                Product::where('category_id', $dup->id)->update(['category_id' => $keep->id]);
                Category::where('parent_id', $dup->id)->update(['parent_id' => $keep->id]);

                if (! $keep->cj_id && $dup->cj_id) {
                    $keep->cj_id = $dup->cj_id;
                    $keep->save();
                }

                $dup->delete();
            });

            $totalDeleted++;
        }
    }

    if ($dryRun) {
        $this->info(sprintf('Dry-run complete. %d duplicate groups detected.', $totalGroups));
    } else {
        $this->info(sprintf('Dedup complete. Removed %d duplicate rows across %d groups.', $totalDeleted, $totalGroups));
    }
})->purpose('Merge duplicate categories by name and parent');

Artisan::command('categories:fix {--force}', function () {
    $force = (bool) $this->option('force');

    if (! $force) {
        $this->warn('⚠️  This will:');
        $this->warn('  1. Sync CJ category tree');
        $this->warn('  2. Merge duplicate categories');
        $this->warn('  3. Update all products with correct categories');

        if (! $this->confirm('Continue?')) {
            $this->info('Cancelled.');
            return;
        }
    }

    $this->info('Step 1: Syncing CJ categories...');
    $this->call('cj:sync-categories');

    $this->info('Step 2: Merging duplicate categories...');
    $this->call('categories:dedupe');

    $this->info('✅ Category fix complete!');
    $this->info('Next: Re-import products with: php artisan cj:sync-my-products --force-update');
})->purpose('Fix all category hierarchy issues (sync, dedupe, repair)');

Artisan::command('cj:import-snapshots {--limit=200}', function () {
    $limit = (int) $this->option('limit');
    $this->call(\App\Console\Commands\CjImportSnapshots::class, ['--limit' => $limit]);
})->purpose('Import CJ snapshots into Category/Product tables');

Schedule::call(function () {
    $cjJobs = FulfillmentJob::query()
        ->whereNull('fulfilled_at')
        ->where('status', '!=', 'failed')
        ->whereHas('provider', fn ($q) => $q->where('driver_class', \App\Domain\Fulfillment\Strategies\CJDropshippingFulfillmentStrategy::class))
        ->limit(50)
        ->pluck('id');

    foreach ($cjJobs as $jobId) {
        dispatch(new PollCJFulfillmentStatus($jobId))->onConnection('database')->onQueue('default');
    }
})->everyMinute()->name('cj:poll-fulfillment');
Schedule::command('queue:work --tries=3 --timeout=120   --stop-when-empty --queue=default')->everyMinute();
