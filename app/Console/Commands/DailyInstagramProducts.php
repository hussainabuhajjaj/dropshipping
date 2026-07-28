<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Models\Product;
use App\Models\InstagramPost;
use App\Mail\DailyInstagramMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use ZipArchive;

class DailyInstagramProducts extends Command
{
    protected $signature = 'dropshipping:daily-instagram
        {--date= : Post date (default: today)}
        {--dry-run : Preview without saving}
        {--send-webhook= : Send daily picks to a webhook URL}
        {--send-email= : Email address for daily content}';

    protected $description = 'Pick 3 products for Instagram, rotating categories daily, no repeats';

    private const CAPTIONS = [
        'default' => [
            "✨ New drop! {name} — just \${price}\n\nShop the look at the link in bio!",
            "🔥 You asked, we delivered! {name} is now available for \${price}\n\nTap to shop 👆",
            "💫 Upgrade your style with {name} — only \${price}\n\nFree shipping on orders over \$50!",
            "🛍️ Psst… your new favorite {piece} just dropped at \${price}",
            "⭐ Customer fave alert! {name} — \${price} and ready to ship",
        ],
        'women' => [
            "👗 Slay the day in {name} — \${price}\n\nTag a friend who needs this!",
            "🌸 Spring in your step with {name} — only \${price}\n\nLink in bio to shop",
            "💃 Head-turner alert! {name} at \${price}\n\nFree shipping available",
        ],
        'men' => [
            "👔 Level up your fit with {name} — \${price}\n\nFresh drip, low price",
            "🔵 Clean. Simple. {name}. \${price}\n\nTap to grab yours",
            "🏆 Best seller for a reason. {name} at \${price}",
        ],
        'kids' => [
            "👶 Too cute! {name} — just \${price}\n\nYour little one needs this",
            "🧸 Adorable and affordable: {name} at \${price}\n\nShop kids collection",
            "🌈 Let them shine in {name} — \${price}\n\nLink in bio",
        ],
        'accessories' => [
            "✨ The perfect finishing touch: {name} — \${price}",
            "💎 Accessories make the outfit. {name} at \${price}",
            "🎒 Your new everyday essential: {name} — \${price}",
        ],
    ];

    private const CATEGORY_SCHEDULE = [
        1 => 'women',
        2 => 'men',
        3 => 'women',
        4 => 'accessories',
        5 => 'women',
        6 => 'kids',
        0 => 'accessories',
    ];

    public function handle(): int
    {
        $date = $this->option('date') ? now()->parse($this->option('date')) : now()->startOfDay();
        $dryRun = (bool) $this->option('dry-run');
        $webhookUrl = $this->option('send-webhook');
        $emailTo = $this->option('send-email');
        $dayOfWeek = (int) $date->format('w');
        $categorySlug = self::CATEGORY_SCHEDULE[$dayOfWeek] ?? 'women';

        $this->info("📅 {$date->format('l, F j, Y')} — Category: {$categorySlug}");
        $this->newLine();

        $postedIds = InstagramPost::where('posted_date', $date)->pluck('product_id');

        if ($postedIds->isNotEmpty() && $postedIds->count() >= 3) {
            $this->info("✅ Already posted 3 products for {$date->format('Y-m-d')}");
            $existing = InstagramPost::with('product')
                ->where('posted_date', $date)
                ->orderBy('day_rank')
                ->get()
                ->map(fn ($post) => [
                    'rank' => $post->day_rank,
                    'category' => $post->category_slug ?? 'mixed',
                    'name' => $post->product?->name ?? 'Unknown',
                    'product_code' => $post->product?->code ?? '',
                    'price' => '$' . number_format((float) ($post->product?->selling_price ?? 0), 2),
                    'margin' => '—',
                    'product_url' => url('/products/' . $post->product_id),
                    'image_url' => $post->image_url,
                    'caption' => $post->caption ?? '',
                    'hashtags' => $post->hashtags ?? '',
                ]);
            $this->displayResults($existing, $dryRun);
            return self::SUCCESS;
        }

        $alreadyPostedIds = InstagramPost::query()
            ->select('product_id')
            ->distinct()
            ->pluck('product_id');

        $excludedIds = $alreadyPostedIds->merge($postedIds)->unique()->values()->all();

        $products = $this->fetchProducts($categorySlug, $excludedIds, 3);

        if ($products->isEmpty()) {
            $products = $this->fetchProducts(null, $excludedIds, 3);

            if ($products->isEmpty()) {
                $this->warn('No products found. Reset posting history first.');
                return self::SUCCESS;
            }
            $categorySlug = 'mixed';
        }

        $results = [];

        foreach ($products as $rank => $item) {
            $product = $item['product'];
            $dayRank = $rank + 1;
            $image = $product->images->first();
            $hashtags = $this->buildHashtags($product, $categorySlug);
            $caption = $this->buildCaption($product, $item, $categorySlug);

            if (! $dryRun) {
                InstagramPost::create([
                    'product_id' => $product->id,
                    'posted_date' => $date,
                    'day_rank' => $dayRank,
                    'category_slug' => $categorySlug,
                    'image_url' => $image?->url,
                    'caption' => $caption,
                    'hashtags' => $hashtags,
                    'quality_score' => $item['quality_score'],
                ]);
            }

            $results[] = [
                'rank' => $dayRank,
                'product_code' => $product->code ?? '',
                'cj_pid' => $product->cj_pid ?? '',
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => '$' . number_format((float) ($product->selling_price ?? 0), 2),
                'margin' => $item['margin'] . '%',
                'image_url' => $image?->url,
                'product_url' => url('/products/' . $product->id),
                'caption' => $caption,
                'hashtags' => $hashtags,
                'category' => $categorySlug,
            ];
        }

        $this->displayResults($results, $dryRun);

        $this->savePublicFeed($date);

        if ($webhookUrl) {
            $this->sendWebhook($results, $webhookUrl);
        }

        if ($emailTo) {
            $this->sendEmail($results, $date, $dryRun, $emailTo);
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('⚠️ Dry run — nothing was saved.');
        } else {
            $this->newLine();
            $this->info("✅ Saved " . count($results) . " Instagram posts for {$date->format('Y-m-d')}");
        }

        return self::SUCCESS;
    }

    private function fetchProducts(?string $categorySlug, array $excludeIds, int $count): \Illuminate\Support\Collection
    {
        $query = Product::query()
            ->whereCjImported()
            ->where('is_active', true)
            ->whereNotIn('id', $excludeIds)
            ->with(['images' => fn ($q) => $q->orderBy('position'), 'variants', 'category'])
            ->withQualityScore()
            ->whereHas('images');

        if ($categorySlug) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $categorySlug));
        }

        $products = $query->get()->filter(function (Product $p) {
            return ($p->quality_score ?? 0) >= 50
                && $this->calculateMargin($p) >= 15
                && $p->images->isNotEmpty();
        })->sortByDesc(function (Product $p) {
            return ($p->quality_score ?? 0) * 0.7 + $this->calculateMargin($p) * 0.3;
        })->take($count)->values();

        return $products->map(fn (Product $p) => [
            'product' => $p,
            'margin' => $this->calculateMargin($p),
            'quality_score' => $p->quality_score ?? 0,
        ]);
    }

    private function buildCaption(Product $product, array $item, string $category): string
    {
        $price = number_format((float) ($product->selling_price ?? 0), 2);
        $name = $product->name;
        $piece = $this->getPieceName($product);

        $pool = self::CAPTIONS[$category] ?? self::CAPTIONS['default'];
        $pool = array_merge($pool, self::CAPTIONS['default']);

        $caption = $pool[array_rand($pool)];
        $caption = str_replace(['{name}', '{price}', '{piece}'], [$name, $price, $piece], $caption);

        $url = url('/products/' . $product->id);

        return $caption . "\n\n🔗 {$url}";
    }

    private function buildHashtags(Product $product, string $category): string
    {
        $tags = ['#simbazustyle'];

        $dayTags = [
            'women' => ['#womensfashion', '#styleinspo', '#outfitoftheday', '#fashionblogger'],
            'men' => ['#mensfashion', '#streetwear', '#menstyle', '#dapper'],
            'kids' => ['#kidsfashion', '#momlife', '#toddlerstyle', '#familyfashion'],
            'accessories' => ['#accessories', '#baggoals', '#watchaddict', '#jewelry'],
        ];

        $tags = array_merge($tags, $dayTags[$category] ?? ['#fashion', '#style']);

        $general = ['#dropshipping', '#onlineshopping', '#newarrivals', '#musthave', '#shopnow'];
        shuffle($general);
        $tags = array_merge($tags, array_slice($general, 0, 3));

        return implode(' ', array_unique($tags));
    }

    private function getPieceName(Product $product): string
    {
        $name = strtolower($product->name ?? '');
        if (str_contains($name, 'dress')) return 'dress';
        if (str_contains($name, 'shoe') || str_contains($name, 'sneaker')) return 'pair of shoes';
        if (str_contains($name, 'bag') || str_contains($name, 'purse')) return 'bag';
        if (str_contains($name, 'watch')) return 'watch';
        if (str_contains($name, 'shirt') || str_contains($name, 'top') || str_contains($name, 'blouse')) return 'top';
        if (str_contains($name, 'pant') || str_contains($name, 'jean') || str_contains($name, 'trouser')) return 'pants';
        if (str_contains($name, 'jacket') || str_contains($name, 'coat')) return 'jacket';
        if (str_contains($name, 'necklace') || str_contains($name, 'earring') || str_contains($name, 'ring')) return 'accessory';
        return 'piece';
    }

    private function calculateMargin(Product $product): float
    {
        $cost = (float) ($product->cost_price ?? 0);
        $price = (float) ($product->selling_price ?? 0);
        if ($cost <= 0 || $price <= 0) return 0;
        return round((($price - $cost) / $price) * 100, 1);
    }

    private function displayResults($results, bool $dryRun): void
    {
        if ($dryRun) {
            $this->warn('⚠️ DRY RUN MODE — Nothing will be saved');
            $this->newLine();
        }

        foreach ($results as $r) {
            $r = (array) $r;
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->line("  📸 Post #{$r['rank']} | {$r['category']}");
            $this->line("  📦 {$r['name']}");
            $this->line("  🔑 Code: {$r['product_code']}");
            $this->line("  💰 {$r['price']} | Margin: {$r['margin']}");
            $this->line("  🔗 {$r['product_url']}");
            if (! empty($r['image_url'])) {
                $this->line("  🖼️ {$r['image_url']}");
            }
            $this->newLine();
            $this->line("  📝 Caption:");
            $this->line("  {$r['caption']}");
            $this->newLine();
            $this->line("  🏷️ Hashtags:");
            $this->line("  {$r['hashtags']}");
        }
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    }

    private function savePublicFeed($date): void
    {
        try {
            $posts = InstagramPost::with('product.images')
                ->where('posted_date', $date)
                ->orderBy('day_rank')
                ->get()
                ->map(fn ($post) => [
                    'date' => $post->posted_date->format('Y-m-d'),
                    'rank' => $post->day_rank,
                    'product' => [
                        'id' => $post->product_id,
                        'name' => $post->product?->name,
                        'price' => $post->product?->selling_price,
                        'url' => url('/products/' . $post->product_id),
                    ],
                    'image_url' => $post->image_url,
                    'caption' => $post->caption,
                    'hashtags' => explode(' ', $post->hashtags ?? ''),
                ]);

            $all = [
                'date' => $date->format('Y-m-d'),
                'category' => $posts->first()?->category_slug ?? 'mixed',
                'posts' => $posts->values()->all(),
            ];

            $path = public_path('instagram-daily.json');
            file_put_contents($path, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $this->info("🌐 Public feed: " . url('instagram-daily.json'));
        } catch (\Throwable $e) {
            Log::warning('Failed to save public Instagram feed', ['error' => $e->getMessage()]);
        }
    }

    private function sendWebhook(array $results, string $url): void
    {
        try {
            Http::timeout(10)->post($url, [
                'event' => 'daily_instagram_products',
                'date' => now()->format('Y-m-d'),
                'products' => $results,
            ]);
            $this->info("📡 Webhook sent to {$url}");
        } catch (\Throwable $e) {
            $this->warn("Webhook failed: {$e->getMessage()}");
        }
    }

    private function sendEmail(array $results, \Carbon\Carbon $date, bool $dryRun, string $emailTo): void
    {
        $dateStr = $date->format('Y-m-d');
        $zipPath = storage_path("app/instagram-{$dateStr}.zip");
        $txtPath = storage_path("app/instagram-{$dateStr}.txt");

        try {
            $this->line("📧 Preparing email for {$emailTo}...");

            $txt = "Daily Instagram Products — {$dateStr}\n";
            $txt .= str_repeat('=', 50) . "\n\n";

            foreach ($results as $r) {
                $txt .= "Post #{$r['rank']} | {$r['category']}\n";
                $txt .= str_repeat('-', 30) . "\n";
                $txt .= "Product: {$r['name']} (Code: {$r['product_code']})\n";
                $txt .= "CJ PID: {$r['cj_pid']}\n";
                $txt .= "Price: {$r['price']} | Margin: {$r['margin']}\n";
                $txt .= "URL: {$r['product_url']}\n\n";
                $txt .= "Caption:\n{$r['caption']}\n\n";
                $txt .= "Hashtags:\n{$r['hashtags']}\n\n";
            }

            file_put_contents($txtPath, $txt);
            $this->info("  📝 Content saved: {$txtPath}");

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Failed to create zip archive');
            }

            $zip->addFile($txtPath, 'content.txt');

            foreach ($results as $r) {
                if (empty($r['image_url'])) continue;

                $resp = Http::timeout(25)->retry(2, 250)->get($r['image_url']);
                if (! $resp->successful()) {
                    $this->warn("  ⚠️ Failed to download image #{$r['rank']}: HTTP {$resp->status()}");
                    continue;
                }

                $ext = pathinfo(parse_url($r['image_url'], PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
                $ext = $ext ?: 'jpg';
                $name = "product-{$r['rank']}-{$r['product_id']}.{$ext}";
                $zip->addFromString($name, $resp->body());
                $this->info("  🖼️ Added {$name} to zip");
            }

            $zip->close();
            $this->info("  📦 Zip saved: {$zipPath}");

            if (! $dryRun) {
                Mail::to($emailTo)->send(new DailyInstagramMail($dateStr, $zipPath, $txtPath));
                $this->info("  ✉️ Email sent to {$emailTo}");
            }

            @unlink($zipPath);
            @unlink($txtPath);
        } catch (\Throwable $e) {
            Log::error('Failed to send daily Instagram email', ['error' => $e->getMessage()]);
            $this->warn("  ❌ Email failed: {$e->getMessage()}");

            @unlink($zipPath ?? '');
            @unlink($txtPath ?? '');
        }
    }
}
