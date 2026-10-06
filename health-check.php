<?php
// run from project root: php health-check.php

putenv('APP_ENV=local');
$_SERVER['HTTP_HOST'] = 'simbazu.test';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

// We'll just boot and query directly without a command.
// But tinker-style: use app() container.
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Direct queries
use Illuminate\Support\Facades\DB;
use App\Models\StorefrontCampaign;
use App\Models\Promotion;
use App\Models\Coupon;
use App\Models\StorefrontCollection;

$now = now();

echo "=== Simbazu Campaign Health Check ===\n";
echo "Date: {$now->timezone('UTC')->format('Y-m-d H:i:s T')}\n\n";

// 1. Campaigns
$campaigns = StorefrontCampaign::query()
    ->orderByDesc('priority')
    ->orderByDesc('id')
    ->get();

echo "Total campaigns: {$campaigns->count()}\n\n";

foreach ($campaigns as $campaign) {
    echo "--- Campaign #{$campaign->id}: {$campaign->name} ---\n";
    echo "  Slug: {$campaign->slug}\n";
    echo "  Type: {$campaign->type}\n";
    echo "  Status: {$campaign->status}\n";
    echo "  is_active: {$campaign->is_active}\n";
    echo "  Priority: {$campaign->priority}\n";

    $schedule = $campaign->resolveScheduleForLocale('en');
    echo "  Schedule: ";
    if ($schedule['starts_at']) echo "starts {$schedule['starts_at']->format('Y-m-d H:i')} ";
    if ($schedule['ends_at']) echo "ends {$schedule['ends_at']->format('Y-m-d H:i')} ";
    echo "tz: {$schedule['timezone']}\n";

    $activeForNow = $campaign->isActiveForLocale('en', $now);
    echo "  Active for 'en' NOW: " . ($activeForNow ? 'YES' : 'NO') . "\n";

    echo "  Hero image: " . ($campaign->hero_image ?: '(none)') . "\n";
    echo "  Hero kicker: " . ($campaign->hero_kicker ?: '(none)') . "\n";
    echo "  Hero subtitle: " . substr($campaign->hero_subtitle ?? '', 0, 100) . "\n";

    $promoIds = $campaign->promotionIds();
    $couponIds = $campaign->couponIds();
    $bannerIds = $campaign->bannerIds();
    $collectionIds = $campaign->collectionIds();

    echo "  Promotion IDs: " . json_encode($promoIds) . " (count: " . count($promoIds) . ")\n";
    echo "  Coupon IDs: " . json_encode($couponIds) . " (count: " . count($couponIds) . ")\n";
    echo "  Banner IDs: " . json_encode($bannerIds) . " (count: " . count($bannerIds) . ")\n";
    echo "  Collection IDs: " . json_encode($collectionIds) . " (count: " . count($collectionIds) . ")\n";

    // Check each linked entity
    if (count($promoIds) > 0) {
        $promos = Promotion::whereIn('id', $promoIds)->get();
        $validPromos = $promos->filter(fn($p) => $p->is_active && (!$p->start_at || $p->start_at <= $now) && (!$p->end_at || $p->end_at >= $now));
        $promoWithTargets = $promos->filter(fn($p) => $p->targets && $p->targets->count() > 0);
        echo "    Promotions found: {$promos->count()}, currently active: {$validPromos->count()}, with targets: {$promoWithTargets->count()}\n";
        foreach ($promos as $p) {
            $isValid = $p->is_active && (!$p->start_at || $p->start_at <= $now) && (!$p->end_at || $p->end_at >= $now);
            echo "      - Promo #{$p->id} \"{$p->name}\": type={$p->type}, value_type={$p->value_type}, value={$p->value}, active={$p->is_active}, valid_now=" . ($isValid ? 'YES' : 'NO') . ", targets=" . ($p->targets ? $p->targets->count() : 0) . "\n";
        }
    } else {
        echo "    ⚠ NO promotions linked\n";
    }

    if (count($couponIds) > 0) {
        $coupons = Coupon::whereIn('id', $couponIds)->get();
        $validCoupons = $coupons->filter(fn($c) => $c->isCurrentlyValid());
        echo "    Coupons found: {$coupons->count()}, currently valid: {$validCoupons->count()}\n";
        foreach ($coupons as $c) {
            $isValid = $c->isCurrentlyValid();
            echo "      - Coupon #{$c->id} code=\"{$c->code}\": type={$c->type}, amount={$c->amount}, active={$c->is_active}, valid_now=" . ($isValid ? 'YES' : 'NO') . ", uses={$c->uses}/" . ($c->max_uses ?: '∞') . "\n";
        }
    } else {
        echo "    ⚠ NO coupons linked\n";
    }

    if (count($collectionIds) > 0) {
        $collections = StorefrontCollection::whereIn('id', $collectionIds)->get();
        $validCols = $collections->filter(fn($col) => $col->isActiveForLocale('en', $now));
        $colsWithProducts = $collections->map(fn($col) => ['id' => $col->id, 'title' => $col->title, 'product_count' => $col->products->count()]);
        echo "    Collections found: {$collections->count()}, currently active: {$validCols->count()}\n";
        foreach ($collections as $col) {
            $isValid = $col->isActiveForLocale('en', $now);
            $prodCount = $col->products->count();
            echo "      - Collection #{$col->id} \"{$col->title}\": slug={$col->slug}, active={$col->is_active}, valid_now=" . ($isValid ? 'YES' : 'NO') . ", products={$prodCount}\n";
            echo "        Hero image: " . ($col->hero_image ?: '(none)') . "\n";
        }
    } else {
        echo "    ⚠ NO collections linked\n";
    }

    if (count($bannerIds) > 0) {
        $banners = \App\Models\StorefrontBanner::whereIn('id', $bannerIds)->get();
        echo "    Banners found: {$banners->count()}\n";
        foreach ($banners as $b) {
            echo "      - Banner #{$b->id} \"{$b->title}\": image=" . ($b->image_path ?: '(none)') . ", cta=" . ($b->cta_text ?: '(none)') . "\n";
        }
    } else {
        echo "    ⚠ NO banners linked\n";
    }

    echo "\n";
}

// Summary of issues
echo "=== ISSUES FOUND ===\n";
$issues = [];

foreach ($campaigns as $campaign) {
    $activeForNow = $campaign->isActiveForLocale('en', $now);

    if (!$activeForNow) {
        $reason = [];
        if (!$campaign->is_active) $reason[] = 'is_active=false';
        if (!in_array($campaign->status, ['active','approved','scheduled'])) $reason[] = "status={$campaign->status}";
        if (!$campaign->isVisibleForLocale('en')) $reason[] = 'locale not visible';
        if ($campaign->resolveScheduleForLocale('en')['starts_at'] && $now->lt($campaign->resolveScheduleForLocale('en')['starts_at'])) $reason[] = 'not started yet';
        if ($campaign->resolveScheduleForLocale('en')['ends_at'] && $now->gt($campaign->resolveScheduleForLocale('en')['ends_at'])) $reason[] = 'ended';
        $issues[] = "Campaign #{$campaign->id} ({$campaign->name}): NOT active — " . implode(', ', $reason);
    }

    $promoIds = $campaign->promotionIds();
    if (count($promoIds) === 0) {
        $issues[] = "Campaign #{$campaign->id} ({$campaign->name}): NO promotions linked";
    } else {
        $promos = Promotion::whereIn('id', $promoIds)->get();
        $valid = $promos->filter(fn($p) => $p->is_active && (!$p->start_at || $p->start_at <= $now) && (!$p->end_at || $p->end_at >= $now));
        if ($valid->count() === 0) {
            $issues[] = "Campaign #{$campaign->id} ({$campaign->name}): ALL linked promotions are invalid right now";
        }
    }

    $couponIds = $campaign->couponIds();
    if (count($couponIds) > 0) {
        $coupons = Coupon::whereIn('id', $couponIds)->get();
        $valid = $coupons->filter(fn($c) => $c->isCurrentlyValid());
        if ($valid->count() !== count($couponIds)) {
            $issues[] = "Campaign #{$campaign->id} ({$campaign->name}): " . (count($couponIds) - $valid->count()) . " of " . count($couponIds) . " coupons invalid";
        }
    }

    $collectionIds = $campaign->collectionIds();
    if (count($collectionIds) === 0) {
        $issues[] = "Campaign #{$campaign->id} ({$campaign->name}): NO collections linked";
    } else {
        $collections = StorefrontCollection::whereIn('id', $collectionIds)->get();
        $valid = $collections->filter(fn($col) => $col->isActiveForLocale('en', $now));
        if ($valid->count() === 0) {
            $issues[] = "Campaign #{$campaign->id} ({$campaign->name}): ALL linked collections invalid";
        }
        foreach ($collections as $col) {
            if ($col->products->count() === 0) {
                $issues[] = "Campaign #{$campaign->id} → Collection #{$col->id} ({$col->title}): EMPTY (no products)";
            }
            if ($col->hero_image && !str_starts_with($col->hero_image, 'http')) {
                // check file exists
                if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($col->hero_image)) {
                    $issues[] = "Campaign #{$campaign->id} → Collection #{$col->id} ({$col->title}): hero_image missing on disk ({$col->hero_image})";
                }
            }
        }
    }

    if ($campaign->hero_image && !str_starts_with($campaign->hero_image, 'http')) {
        if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($campaign->hero_image)) {
            $issues[] = "Campaign #{$campaign->id} ({$campaign->name}): hero_image missing on disk ({$campaign->hero_image})";
        }
    }
}

if (empty($issues)) {
    echo "✓ No issues found.\n";
} else {
    foreach ($issues as $i => $issue) {
        echo ($i+1) . ". $issue\n";
    }
}

echo "\n=== END ===\n";
