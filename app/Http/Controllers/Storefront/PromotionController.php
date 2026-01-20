<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\FormatsCategories;
use App\Http\Controllers\Storefront\Concerns\TransformsProducts;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Services\Promotions\PromotionDisplayService;
use App\Services\Promotions\PromotionHomepageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PromotionController extends Controller
{
    use TransformsProducts;
    use FormatsCategories;

    public function index(Request $request)
    {
        $now = now();
        $promotions = Promotion::where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('start_at')->orWhere('start_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_at')->orWhere('end_at', '>=', $now);
            })
            ->orderByDesc('priority')
            ->with(['targets', 'conditions'])
            ->get();

        $displayService = app(PromotionDisplayService::class);

        return Inertia::render('Promotions/Index', [
            'promotions' => $promotions->map(fn (Promotion $promo) => $displayService->serializePromotion($promo))->values()->all(),
        ]);
    }

    public function flashSales(Request $request)
    {
        $promotions = $this->activePromotions()
            ->where('type', 'flash_sale')
            ->orderByDesc('priority')
            ->get();

        return Inertia::render('Promotions/FlashSales', [
            'promotions' => $this->serializePromotions($promotions),
        ]);
    }

    public function deals(Request $request)
    {
        $promotions = $this->activePromotions()
            ->where('type', 'auto_discount')
            ->orderByDesc('priority')
            ->get();

        return Inertia::render('Promotions/Deals', [
            'promotions' => $this->serializePromotions($promotions),
        ]);
    }

    public function promotedProducts(Request $request)
    {
        $promotions = $this->activePromotions()->get();
        $productIds = $promotions->flatMap(fn (Promotion $promo) => $promo->targets)
            ->filter(fn ($target) => $target->target_type === 'product')
            ->pluck('target_id')
            ->unique()
            ->values()
            ->all();

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->where('is_active', true)
            ->with(['images', 'category', 'variants', 'translations'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->get()
            ->map(fn (Product $product) => $this->transformProduct($product))
            ->values()
            ->all();

        $categoryIds = collect($products)->pluck('category_id')->filter()->unique()->values()->all();
        $displayPromotions = app(PromotionHomepageService::class)->getPromotionsForPlacement('product', $productIds, $categoryIds);

        return Inertia::render('Promotions/PromotedProducts', [
            'products' => $products,
            'promotions' => $displayPromotions,
            'currency' => 'USD',
        ]);
    }

    public function promotedCategories(Request $request)
    {
        $locale = app()->getLocale();
        $promotions = $this->activePromotions()->get();
        $categoryIds = $promotions->flatMap(fn (Promotion $promo) => $promo->targets)
            ->filter(fn ($target) => $target->target_type === 'category')
            ->pluck('target_id')
            ->unique()
            ->values()
            ->all();

        $categories = Category::query()
            ->whereIn('id', $categoryIds)
            ->withCount(['products as products_count' => fn ($q) => $q->where('is_active', true)])
            ->with(['translations' => fn ($q) => $q->where('locale', $locale)])
            ->orderByDesc('view_count')
            ->orderByDesc('products_count')
            ->get()
            ->map(function (Category $category) use ($locale) {
                $image = $category->hero_image;
                if ($image && ! str_starts_with($image, 'http://') && ! str_starts_with($image, 'https://')) {
                    $image = Storage::url($image);
                }

                return [
                    'id' => $category->id,
                    'name' => $category->translatedValue('name', $locale),
                    'slug' => $category->slug,
                    'count' => $category->products_count ?? 0,
                    'image' => $image,
                ];
            })
            ->values()
            ->all();

        $displayPromotions = app(PromotionHomepageService::class)->getPromotionsForPlacement('category', [], $categoryIds);

        return Inertia::render('Promotions/PromotedCategories', [
            'categories' => $categories,
            'promotions' => $displayPromotions,
        ]);
    }

    private function activePromotions()
    {
        // NOTE: This active/dated promotion query is duplicated in PromotionEngine
        // and PromotionDisplayService. Keep filters aligned when changing.
        $now = now();
        return Promotion::query()
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('start_at')->orWhere('start_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_at')->orWhere('end_at', '>=', $now);
            })
            ->with(['targets', 'conditions']);
    }

    private function serializePromotions($promotions): array
    {
        $displayService = app(PromotionDisplayService::class);
        return $promotions->map(fn (Promotion $promo) => $displayService->serializePromotion($promo))->values()->all();
    }
}
