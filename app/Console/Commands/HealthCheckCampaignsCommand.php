<?php

namespace App\Console\Commands;

use App\Models\StorefrontCampaign;
use App\Models\Promotion;
use App\Models\Coupon;
use App\Models\StorefrontCollection;
use App\Models\StorefrontBanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class HealthCheckCampaignsCommand extends Command
{
    protected $signature = 'app:health-check-campaigns';
    protected $description = 'Check campaign health: active campaigns, products, hero images, collections, prices, coupon codes';

    public function handle(): int
    {
        $now = now();
        $issues = [];
        $detail = [];

        $this->info("=== Simbazu Campaign Health Check ===");
        $this->info("Date: {$now->timezone('UTC')->format('Y-m-d H:i:s T')}");
        $this->newLine();

        $campaigns = StorefrontCampaign::query()
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get();

        $this->info("Total campaigns: {$campaigns->count()}");
        $this->newLine();

        foreach ($campaigns as $campaign) {
            $cIssues = [];
            $detail[] = "--- Campaign #{$campaign->id}: {$campaign->name} ---";
            $detail[] = "  Slug: {$campaign->slug}";
            $detail[] = "  Type: {$campaign->type}";
            $detail[] = "  Status: {$campaign->status}";
            $detail[] = "  is_active: {$campaign->is_active}";
            $detail[] = "  Priority: {$campaign->priority}";

            $schedule = $campaign->resolveScheduleForLocale('en');
            $dateStr = '';
            if ($schedule['starts_at']) $dateStr .= "starts {$schedule['starts_at']->format('Y-m-d H:i')} ";
            if ($schedule['ends_at']) $dateStr .= "ends {$schedule['ends_at']->format('Y-m-d H:i')} ";
            $dateStr .= "tz: {$schedule['timezone']}";
            $detail[] = "  Schedule: {$dateStr}";

            $activeForNow = $campaign->isActiveForLocale('en', $now);
            $detail[] = "  Active for 'en' NOW: " . ($activeForNow ? 'YES' : 'NO');

            $detail[] = "  Hero image: " . ($campaign->hero_image ?: '(none)');
            $detail[] = "  Hero kicker: " . ($campaign->hero_kicker ?: '(none)');
            $detail[] = "  Hero subtitle: " . substr($campaign->hero_subtitle ?? '', 0, 120);

            $promoIds = $campaign->promotionIds();
            $couponIds = $campaign->couponIds();
            $bannerIds = $campaign->bannerIds();
            $collectionIds = $campaign->collectionIds();

            $detail[] = "  Promotion IDs: " . json_encode($promoIds) . " (count: " . count($promoIds) . ")";
            $detail[] = "  Coupon IDs: " . json_encode($couponIds) . " (count: " . count($couponIds) . ")";
            $detail[] = "  Banner IDs: " . json_encode($bannerIds) . " (count: " . count($bannerIds) . ")";
            $detail[] = "  Collection IDs: " . json_encode($collectionIds) . " (count: " . count($collectionIds) . ")";

            // --- PROMOTIONS ---
            if (count($promoIds) === 0) {
                $cIssues[] = "NO promotions linked";
                $detail[] = "  ⚠ NO promotions linked";
            } else {
                $promos = Promotion::whereIn('id', $promoIds)->get();
                $validPromos = $promos->filter(fn($p) => $p->is_active && (!$p->start_at || $p->start_at <= $now) && (!$p->end_at || $p->end_at >= $now));
                $promosWithTargets = $promos->filter(fn($p) => $p->targets && $p->targets->count() > 0);
                $detail[] = "  Promotions found: {$promos->count()}, currently active: {$validPromos->count()}, with targets: {$promosWithTargets->count()}";

                foreach ($promos as $p) {
                    $isValid = $p->is_active && (!$p->start_at || $p->start_at <= $now) && (!$p->end_at || $p->end_at >= $now);
                    $targetInfo = $p->targets && $p->targets->count() > 0
                        ? $p->targets->map(fn($t) => "{$t->target_type}:{$t->target_id}")->implode(', ')
                        : 'none';
                    $detail[] = "    - Promo #{$p->id} \"{$p->name}\": type={$p->type}, value_type={$p->value_type}, value={$p->value}, active={$p->is_active}, valid_now=" . ($isValid ? 'YES' : 'NO') . ", targets=[{$targetInfo}]";

                    if ($p->targets && $p->targets->count() > 0) {
                        $targetProducts = $p->targets->filter(fn($t) => $t->target_type === 'product');
                        if ($targetProducts->isNotEmpty()) {
                            $missingProducts = [];
                            foreach ($targetProducts as $tp) {
                                $prod = \App\Models\Product::find($tp->target_id);
                                if (!$prod || !$prod->is_active) {
                                    $missingProducts[] = "#{$tp->target_id}";
                                }
                            }
                            if (!empty($missingProducts)) {
                                $cIssues[] = "Promotion #{$p->id} targets missing/inactive products: " . implode(', ', $missingProducts);
                                $detail[] = "      ⚠ Targets missing/inactive: " . implode(', ', $missingProducts);
                            }
                        }
                    }
                }

                $promoNames = $promos->map(fn($p) => $p->name)->all();
                // Verify that all promo targets map to actual products that exist
                $allTargetIds = $promos->flatMap(fn($p) => $p->targets)->filter(fn($t) => $t->target_type === 'product')->pluck('target_id')->unique();
                if ($allTargetIds->isNotEmpty()) {
                    $existingProducts = \App\Models\Product::whereIn('id', $allTargetIds)->where('is_active', true)->pluck('id');
                    $orphaned = $allTargetIds->diff($existingProducts);
                    if ($orphaned->isNotEmpty()) {
                        $cIssues[] = "Campaign #{$campaign->id}: {$orphaned->count()} promotion targets reference missing/inactive products: IDs " . $orphaned->implode(', ');
                    }
                }
            }

            // --- COUPONS ---
            if (count($couponIds) > 0) {
                $coupons = Coupon::whereIn('id', $couponIds)->get();
                $validCoupons = $coupons->filter(fn($c) => $c->isCurrentlyValid());
                $detail[] = "  Coupons found: {$coupons->count()}, currently valid: {$validCoupons->count()}";
                foreach ($coupons as $c) {
                    $isValid = $c->isCurrentlyValid();
                    $detail[] = "    - Coupon #{$c->id} code=\"{$c->code}\": type={$c->type}, amount={$c->amount}, active={$c->is_active}, valid_now=" . ($isValid ? 'YES' : 'NO') . ", uses={$c->uses}/" . ($c->max_uses ?: '∞');
                    if (!$isValid) {
                        $reason = [];
                        if (!$c->is_active) $reason[] = 'inactive';
                        if ($c->starts_at && $now->lt($c->starts_at)) $reason[] = 'not started';
                        if ($c->ends_at && $now->gt($c->ends_at)) $reason[] = 'expired';
                        if ($c->max_uses && $c->uses >= $c->max_uses) $reason[] = 'max uses reached';
                        $cIssues[] = "Coupon code \"{$c->code}\" invalid: " . implode(', ', $reason);
                    }
                }
                if ($validCoupons->count() !== count($couponIds)) {
                    // already captured above in loop
                }
            } else {
                $detail[] = "  ⚠ NO coupons linked";
            }

            // --- COLLECTIONS ---
            if (count($collectionIds) > 0) {
                $collections = StorefrontCollection::whereIn('id', $collectionIds)->get();
                $validCols = $collections->filter(fn($col) => $col->isActiveForLocale('en', $now));
                $detail[] = "  Collections found: {$collections->count()}, currently active: {$validCols->count()}";

                foreach ($collections as $col) {
                    $isValid = $col->isActiveForLocale('en', $now);
                    $prodCount = $col->products()->count();
                    $detail[] = "    - Collection #{$col->id} \"{$col->title}\": slug={$col->slug}, active={$col->is_active}, valid_now=" . ($isValid ? 'YES' : 'NO') . ", products={$prodCount}";
                    $detail[] = "      Hero image: " . ($col->hero_image ?: '(none)');

                    if ($prodCount === 0) {
                        $cIssues[] = "Collection #{$col->id} \"{$col->title}\" is EMPTY (no products)";
                        $detail[] = "      ⚠ EMPTY — no products";
                    }

                    // Check hero images exist on disk
                    if ($col->hero_image && !str_starts_with($col->hero_image, 'http')) {
                        if (!Storage::disk('public')->exists($col->hero_image)) {
                            $cIssues[] = "Collection #{$col->id} \"{$col->title}\": hero_image missing on disk ({$col->hero_image})";
                            $detail[] = "      ⚠ hero_image missing on disk";
                        }
                    }

                    // Check product prices
                    if ($prodCount > 0) {
                        $products = $col->products;
                        $priceIssues = [];
                        foreach ($products as $prod) {
                            $price = $prod->selling_price;
                            if (empty($price) || !is_numeric($price) || $price <= 0) {
                                $priceIssues[] = "{$prod->name}: selling_price={$price}";
                            }
                            if ($prod->currency && $prod->currency !== 'XOF') {
                                $priceIssues[] = "{$prod->name}: currency={$prod->currency} (expected XOF)";
                            }
                        }
                        if (!empty($priceIssues)) {
                            $cIssues[] = "Collection #{$col->id} \"{$col->title}\": " . count($priceIssues) . " price issues: " . implode('; ', $priceIssues);
                            $detail[] = "      ⚠ Price issues: " . implode('; ', $priceIssues);
                        }
                    }
                }

                if ($validCols->count() === 0 && count($collectionIds) > 0) {
                    $cIssues[] = "ALL linked collections are invalid right now";
                }
            } else {
                $detail[] = "  ⚠ NO collections linked";
            }

            // --- BANNERS ---
            if (count($bannerIds) > 0) {
                $banners = StorefrontBanner::whereIn('id', $bannerIds)->get();
                $detail[] = "  Banners found: {$banners->count()}";
                foreach ($banners as $b) {
                    $detail[] = "    - Banner #{$b->id} \"{$b->title}\": image=" . ($b->image_path ?: '(none)') . ", cta=" . ($b->cta_text ?: '(none)');
                    if ($b->image_path && !str_starts_with($b->image_path, 'http') && !Storage::disk('public')->exists($b->image_path)) {
                        $cIssues[] = "Banner #{$b->id} \"{$b->title}\": image missing on disk ({$b->image_path})";
                        $detail[] = "      ⚠ image missing on disk";
                    }
                }
            } else {
                $detail[] = "  ⚠ NO banners linked";
            }

            // --- HERO IMAGE ---
            if ($campaign->hero_image && !str_starts_with($campaign->hero_image, 'http')) {
                if (!Storage::disk('public')->exists($campaign->hero_image)) {
                    $cIssues[] = "Campaign hero_image missing on disk ({$campaign->hero_image})";
                    $detail[] = "  ⚠ hero_image missing on disk";
                }
            }

            // --- Campaign active status ---
            if (!$activeForNow) {
                $reason = [];
                if (!$campaign->is_active) $reason[] = 'is_active=false';
                if (!in_array($campaign->status, ['active','approved','scheduled'])) $reason[] = "status={$campaign->status}";
                if (!$campaign->isVisibleForLocale('en')) $reason[] = 'locale not visible for en';
                if ($campaign->resolveScheduleForLocale('en')['starts_at'] && $now->lt($campaign->resolveScheduleForLocale('en')['starts_at'])) $reason[] = 'not started yet';
                if ($campaign->resolveScheduleForLocale('en')['ends_at'] && $now->gt($campaign->resolveScheduleForLocale('en')['ends_at'])) $reason[] = 'ended';
                $cIssues[] = "Campaign NOT active for 'en': " . implode(', ', $reason);
            }

            $detail[] = '';
            if (!empty($cIssues)) {
                $issues = array_merge($issues, $cIssues);
            }
        }

        // --- COLLECTION PAGES ACCESSIBLE ---
        $this->newLine();
        $this->info("=== COLLECTION PAGE ACCESSIBILITY ===");
        $allCollectionIds = [];
        foreach ($campaigns as $campaign) {
            foreach ($campaign->collectionIds() as $cid) {
                $allCollectionIds[$cid] = true;
            }
        }
        $allCollectionIds = array_keys($allCollectionIds);
        if (!empty($allCollectionIds)) {
            $collections = StorefrontCollection::whereIn('id', $allCollectionIds)->get();
            foreach ($collections as $col) {
                $url = url("/collections/{$col->slug}");
                $detail[] = "Collection page: {$url}";
                // Can't do HTTP request from CLI easily, but verify slug is non-empty
                if (empty($col->slug)) {
                    $issues[] = "Collection #{$col->id} \"{$col->title}\": slug is empty — page would 404";
                    $detail[] = "  ⚠ EMPTY SLUG — page inaccessible";
                }
            }
        } else {
            $detail[] = "No collections to check.";
        }

        // --- DISPLAY ---
        $this->newLine();
        foreach ($detail as $line) {
            $this->line($line);
        }

        $this->newLine();
        $this->info("=== ISSUES FOUND ===");
        if (empty($issues)) {
            $this->info("✓ No issues found.");
        } else {
            foreach ($issues as $i => $issue) {
                $this->line(($i+1) . ". {$issue}");
            }
            $this->newLine();
            $this->error("Total issues: " . count($issues));
        }
        $this->newLine();
        $this->info("=== END ===");

        return empty($issues) ? 0 : 1;
    }
}
