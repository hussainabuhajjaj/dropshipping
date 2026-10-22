#!/usr/bin/env php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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
    line('Non-XOF supplier/base currency', columnExists('products', 'currency') ? (int) DB::table('products')->where('currency', '!=', 'XOF')->count() : '-');
    line('Missing source URL', columnExists('products', 'source_url') ? (int) DB::table('products')->whereNull('source_url')->count() : '-');
    line('Chinese titles', (int) DB::table('products')->where('name', 'REGEXP', '[一-龥]')->count());

    if (tableExists('product_variants')) {
        line('Variants', (int) DB::table('product_variants')->count());
    }
}

function productOrganizationSuggestions(): void
{
    echo "Simbazu Product Organization Suggestions\n";
    echo "Date: " . now()->format('Y-m-d H:i') . "\n";
    echo "Mode: suggestions only, no catalog changes applied\n\n";

    if (! tableExists('products')) {
        echo "products table not found\n";
        return;
    }

    $categoryAvailable = tableExists('categories') && columnExists('products', 'category_id');
    $hasChineseRegexp = "REGEXP '[一-龥]'";

    line('Products checked', (int) DB::table('products')->count());
    line('Missing category', $categoryAvailable ? (int) DB::table('products')->whereNull('category_id')->count() : '-');
    line('Chinese titles', columnExists('products', 'name') ? (int) DB::table('products')->whereRaw("name {$hasChineseRegexp}")->count() : '-');
    line('Missing/zero price', columnExists('products', 'selling_price') ? (int) DB::table('products')->where(fn ($query) => $query->whereNull('selling_price')->orWhere('selling_price', '<=', 0))->count() : '-');
    line('Non-XOF supplier/base currency', columnExists('products', 'currency') ? (int) DB::table('products')->whereNotNull('currency')->where('currency', '!=', 'XOF')->count() : '-');
    line('1688/CNY source products', count1688CurrencyProducts());

    if (! $categoryAvailable || ! columnExists('products', 'name')) {
        echo "\nNeed products.name, products.category_id, and categories table for category suggestions.\n";
        return;
    }

    $categories = loadSuggestionCategories();

    if ($categories->isEmpty()) {
        echo "\nNo categories found for matching.\n";
        return;
    }

    echo "\nTop category suggestions:\n";
    $shown = 0;
    foreach (candidateProductsForOrganization() as $product) {
        $suggestion = suggestCategoryForProduct((array) $product, $categories);

        if ($suggestion === null) {
            continue;
        }

        $current = $product->category_name ? "{$product->category_name} (#{$product->category_id})" : 'none';
        $reason = implode(', ', array_unique(array_filter([
            $product->category_id ? 'category looks weak' : 'missing category',
            containsChinese((string) $product->name) ? 'Chinese title' : null,
            $suggestion['reason'],
        ])));

        line(
            '#' . $product->id . ' ' . compactProductName((string) $product->name),
            "current {$current} -> {$suggestion['name']} (#{$suggestion['id']}), {$reason}"
        );

        $shown++;

        if ($shown >= 12) {
            break;
        }
    }

    if ($shown === 0) {
        echo "No high-confidence category suggestions found in the sampled products.\n";
    }

    echo "\nTitle cleanup suggestions:\n";
    $titleRows = DB::table('products')
        ->select(['id', 'name'])
        ->whereRaw("name {$hasChineseRegexp}")
        ->orderByDesc('updated_at')
        ->limit(8)
        ->get();

    if ($titleRows->isEmpty()) {
        echo "No Chinese titles found.\n";
    } else {
        $titleRows->each(fn ($row) => line('#' . $row->id, compactProductName((string) $row->name)));
        echo "Suggested action: re-import/translate these titles before featuring them in storefront collections.\n";
    }

    echo "\nPrice/currency checks:\n";
    priceCurrencySuggestionRows()
        ->each(fn ($row) => line('#' . $row->id . ' ' . compactProductName((string) $row->name), priceCurrencyReason((array) $row)));
}

function count1688CurrencyProducts(): int
{
    if (! columnExists('products', 'source_url') && ! columnExists('products', 'supplier_product_url') && ! columnExists('products', 'supplier_currency')) {
        return 0;
    }

    return (int) DB::table('products')
        ->where(function ($query) {
            if (columnExists('products', 'source_url')) {
                $query->orWhere('source_url', 'like', '%1688.com%');
            }
            if (columnExists('products', 'supplier_product_url')) {
                $query->orWhere('supplier_product_url', 'like', '%1688.com%');
            }
            if (columnExists('products', 'supplier_currency')) {
                $query->orWhereIn('supplier_currency', ['CNY', 'RMB', 'CNH']);
            }
        })
        ->count();
}

function candidateProductsForOrganization()
{
    return DB::table('products')
        ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
        ->select([
            'products.id',
            'products.name',
            'products.category_id',
            'products.selling_price',
            'products.currency',
            'products.supplier_currency',
            'products.source_url',
            'products.supplier_product_url',
            'categories.name as category_name',
        ])
        ->where(function ($query) {
            $query->whereNull('products.category_id')
                ->orWhereRaw("products.name REGEXP '[一-龥]'");
        })
        ->orderByRaw('products.category_id IS NULL DESC')
        ->orderByDesc('products.updated_at')
        ->limit(80)
        ->get();
}

function loadSuggestionCategories()
{
    return DB::table('categories')
        ->select(['id', 'name', 'slug', 'parent_id'])
        ->when(columnExists('categories', 'is_active'), fn ($query) => $query->where('is_active', true))
        ->orderBy('parent_id')
        ->orderBy('name')
        ->get()
        ->map(function ($category) {
            $category->name_tokens = categoryTokens((string) $category->name);
            $category->slug_tokens = categoryTokens((string) $category->slug);

            return $category;
        });
}

function suggestCategoryForProduct(array $product, $categories): ?array
{
    $name = (string) ($product['name'] ?? '');
    $tokens = productTokens($name);
    $aliases = categoryAliasTokens($name);

    if ($tokens === [] && $aliases === []) {
        return null;
    }

    $best = null;
    foreach ($categories as $category) {
        $score = count(array_intersect($tokens, $category->name_tokens)) * 4;
        $score += count(array_intersect($aliases, $category->name_tokens)) * 5;
        $score += categoryMatchBoost($name, (string) $category->name . ' ' . (string) $category->slug);
        $score -= categoryMismatchPenalty($name, (string) $category->name . ' ' . (string) $category->slug);
        $score -= unmatchedSpecificCategoryPenalty($category->name_tokens, $tokens, $aliases);

        if ($score <= 0) {
            continue;
        }

        if ($best === null || $score > $best['score']) {
            $best = [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
                'score' => $score,
                'reason' => $aliases === [] ? 'keyword match' : 'keyword/category alias match',
            ];
        }
    }

    if ($best === null || $best['score'] < 3) {
        return null;
    }

    return $best;
}

function categoryMatchBoost(string $productName, string $categoryText): int
{
    $product = Str::lower(Str::ascii($productName));
    $category = Str::lower(Str::ascii($categoryText));
    $boost = 0;

    if (preg_match('/\b(men|mens|man|male)\b/', $product) && preg_match('/\b(men|mens|man|male)\b/', $category)) {
        $boost += 6;
    }

    if (preg_match('/\b(women|womens|woman|lady|ladies|female)\b/', $product) && preg_match('/\b(women|womens|woman|lady|ladies|female)\b/', $category)) {
        $boost += 6;
    }

    return $boost;
}

function categoryMismatchPenalty(string $productName, string $categoryText): int
{
    $product = Str::lower(Str::ascii($productName));
    $category = Str::lower(Str::ascii($categoryText));
    $penalty = 0;

    if (preg_match('/\b(men|mens|man|male)\b/', $product) && preg_match('/\b(women|womens|woman|lady|ladies|female)\b/', $category)) {
        $penalty += 8;
    }

    if (preg_match('/\b(women|womens|woman|lady|ladies|female)\b/', $product) && preg_match('/\b(men|mens|man|male)\b/', $category)) {
        $penalty += 8;
    }

    if (! str_contains($product, 'baby') && ! str_contains($product, 'kid') && ! str_contains($product, 'child') && preg_match('/\b(baby|kids|children|boys|girls)\b/', $category)) {
        $penalty += 4;
    }

    return $penalty;
}

function unmatchedSpecificCategoryPenalty(array $categoryNameTokens, array $productTokens, array $aliasTokens): int
{
    $matched = array_unique(array_merge($productTokens, $aliasTokens));
    $penalty = 0;

    foreach ($categoryNameTokens as $token) {
        if (in_array($token, $matched, true)) {
            continue;
        }

        $penalty += 3;
    }

    return $penalty;
}

function productTokens(string $value): array
{
    $stopWords = [
        'with', 'for', 'and', 'the', 'new', 'hot', 'set', 'pcs', 'men', 'mens', 'man',
        'women', 'womens', 'woman', 'lady', 'ladies', 'fashion', 'style', 'good', 'best',
        'quality', 'sale', 'selling', 'large', 'small', 'size', 'shoulder',
    ];

    $tokens = array_values(array_unique(array_filter(
        preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($value))) ?: [],
        fn (string $token) => strlen($token) >= 3 && ! in_array($token, $stopWords, true)
    )));

    $expanded = [];
    foreach ($tokens as $token) {
        $expanded[] = $token;

        if (str_ends_with($token, 'ies') && strlen($token) > 4) {
            $expanded[] = substr($token, 0, -3) . 'y';
        } elseif (str_ends_with($token, 's') && strlen($token) > 4) {
            $expanded[] = substr($token, 0, -1);
        } else {
            $expanded[] = $token . 's';
        }
    }

    return array_values(array_unique($expanded));
}

function categoryTokens(string $value): array
{
    return productTokens($value);
}

function categoryAliasTokens(string $name): array
{
    $haystack = Str::lower(Str::ascii($name));
    $aliases = [
        'shoe shoes sneaker sneakers boot boots loafer loafers sandal sandals pump pumps' => ['footwear'],
        'dress dresses skirt skirts blouse blouses shirt shirts jeans pants hoodie hoodies sweatshirt sweatshirts' => ['clothing'],
        'wallet wallets bag bags backpack backpacks luggage purse crossbody tote' => ['bags'],
        'jewelry jewellery necklace necklaces bracelet bracelets ring rings earring earrings watch watches' => ['jewelry'],
        'wig wigs hair extension extensions lace bundle bundles' => ['wigs'],
        'kitchen lunch bento bottle food container cup cookware' => ['home', 'kitchen'],
        'phone phones case charger cable earbud earbuds speaker watch smartwatch camera laptop tablet' => ['electronics'],
        'toy toys baby babies kid kids children stroller school stationery' => ['kids'],
        'beauty makeup skincare hair perfume nail nails' => ['beauty'],
        'car auto motorcycle bike bicycle' => ['automotive'],
        'pet pets dog dogs cat cats' => ['pets'],
        'sport sports fitness gym yoga outdoor camping travel running hiking basketball soccer' => ['sports'],
        'home decor lamp light bedding pillow curtain storage organizer bathroom' => ['home'],
    ];

    $tokens = [];
    foreach ($aliases as $needles => $categoryTokens) {
        foreach (explode(' ', $needles) as $needle) {
            if (str_contains($haystack, $needle)) {
                $tokens = array_merge($tokens, $categoryTokens);
                break;
            }
        }
    }

    return array_values(array_unique($tokens));
}

function containsChinese(string $value): bool
{
    return preg_match('/[\x{4E00}-\x{9FFF}]/u', $value) === 1;
}

function compactProductName(string $name): string
{
    $name = trim(preg_replace('/\s+/', ' ', strip_tags($name)) ?? $name);

    return mb_strimwidth($name, 0, 72, '...');
}

function priceCurrencySuggestionRows()
{
    return DB::table('products')
        ->select(['id', 'name', 'selling_price', 'currency', 'supplier_currency', 'source_url', 'supplier_product_url'])
        ->where(function ($query) {
            if (columnExists('products', 'selling_price')) {
                $query->orWhereNull('selling_price')->orWhere('selling_price', '<=', 0);
            }
            if (columnExists('products', 'supplier_currency')) {
                $query->orWhereIn('supplier_currency', ['CNY', 'RMB', 'CNH']);
            }
            if (columnExists('products', 'source_url')) {
                $query->orWhere('source_url', 'like', '%1688.com%');
            }
            if (columnExists('products', 'supplier_product_url')) {
                $query->orWhere('supplier_product_url', 'like', '%1688.com%');
            }
        })
        ->orderByDesc('updated_at')
        ->limit(8)
        ->get();
}

function priceCurrencyReason(array $row): string
{
    $reasons = [];

    if ((float) ($row['selling_price'] ?? 0) <= 0) {
        $reasons[] = 'missing/zero selling price';
    }

    if (isset($row['currency']) && $row['currency'] !== null && $row['currency'] !== '') {
        $reasons[] = 'supplier/base currency is ' . $row['currency'];
    }

    if (in_array($row['supplier_currency'] ?? null, ['CNY', 'RMB', 'CNH'], true)) {
        $reasons[] = 'Chinese yuan source; verify XOF conversion';
    }

    if (str_contains((string) ($row['source_url'] ?? ''), '1688.com') || str_contains((string) ($row['supplier_product_url'] ?? ''), '1688.com')) {
        $reasons[] = '1688 source';
    }

    return implode(', ', $reasons) ?: 'check pricing manually';
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
    'organize-products', 'organize', 'product-suggestions', 'suggestions' => productOrganizationSuggestions(),
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
