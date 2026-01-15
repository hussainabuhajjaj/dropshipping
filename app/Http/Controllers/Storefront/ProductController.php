<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\FormatsCategories;
use App\Http\Controllers\Storefront\Concerns\TransformsProducts;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Services\ProductRecommendationService;
use App\Services\Promotions\PromotionHomepageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    use TransformsProducts;
    use FormatsCategories;

    public function index(Request $request): Response
    {
        $category = $request->query('category');
        $minPrice = $request->query('min_price');
        $maxPrice = $request->query('max_price');
        $query = $request->query('q');

        $productQuery = Product::query()
            ->where('is_active', true)
            ->with(['images', 'category', 'variants', 'translations'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        if ($category) {
            $productQuery->whereHas('category', function ($builder) use ($category) {
                $builder->where('name', $category)->orWhere('slug', $category);
            });
        }

        if ($minPrice !== null && is_numeric($minPrice)) {
            $productQuery->where('selling_price', '>=', (float) $minPrice);
        }

        if ($maxPrice !== null && is_numeric($maxPrice)) {
            $productQuery->where('selling_price', '<=', (float) $maxPrice);
        }

        if ($query) {
            $productQuery->where(function ($builder) use ($query) {
                $builder
                    ->where('name', 'like', '%' . $query . '%')
                    ->orWhere('description', 'like', '%' . $query . '%');
                $builder->orWhereHas('category', function ($categoryBuilder) use ($query) {
                    $categoryBuilder->where('name', 'like', '%' . $query . '%');
                });
            });
        }

        $perPage = 18;
        $products = $productQuery
            ->latest()
            ->paginate($perPage)
            ->through(fn (Product $product) => $this->transformProduct($product));

        $categories = $this->rootCategoriesTree(['children', 'children.children']);

        $productIds = $products->getCollection()->pluck('id')->all();
        $categoryIds = $products->getCollection()->pluck('category_id')->filter()->unique()->values()->all();
        $promotions = app(PromotionHomepageService::class)->getPromotionsForTargets($productIds, $categoryIds);

        return Inertia::render('Products/Index', [
            'products' => $products,
            'currency' => 'USD',
            'categories' => $categories,
            'promotions' => $promotions,
            'filters' => [
                'category' => $category,
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'q' => $query,
                'page' => $products->currentPage(),
            ],
        ]);
    }

    public function show(Product $product): Response
    {
        abort_if(! $product->is_active, 404);

        $product->load(['images', 'variants', 'category', 'translations']);

        $reviews = ProductReview::query()
            ->with('customer')
            ->where('product_id', $product->id)
            ->where('status', 'approved')
            ->latest()
            ->get();

        $reviewHighlights = $reviews
            ->take(3)
            ->map(fn (ProductReview $review) => [
                'rating' => $review->rating,
                'title' => $review->title,
                'body' => $review->body,
                'author' => $review->customer?->name ?? 'Verified buyer',
            ]);

        $reviewCount = $reviews->count();
        $reviewAverage = $reviewCount ? round($reviews->avg('rating'), 1) : 0.0;
        $reviewBreakdown = collect([5, 4, 3, 2, 1])->mapWithKeys(function ($rating) use ($reviews) {
            return [$rating => $reviews->where('rating', $rating)->count()];
        })->all();

        $recommendationService = app(ProductRecommendationService::class);

        $related = $recommendationService
            ->relatedProducts($product, 4)
            ->map(fn (Product $relatedProduct) => $this->transformProduct($relatedProduct));

        $customer = Auth::guard('customer')->user();
        $reviewableItems = [];

        if ($customer) {
            $reviewableItems = OrderItem::query()
                ->with('order')
                ->where('fulfillment_status', 'fulfilled')
                ->whereHas('shipments', function ($builder) {
                    $builder->whereNotNull('delivered_at');
                })
                ->whereHas('order', function ($builder) use ($customer) {
                    $builder
                        ->where('customer_id', $customer->id)
                        ->where('status', 'fulfilled');
                })
                ->whereHas('productVariant', function ($builder) use ($product) {
                    $builder->where('product_id', $product->id);
                })
                ->whereDoesntHave('review')
                ->latest()
                ->get()
                ->map(fn (OrderItem $item) => [
                    'id' => $item->id,
                    'order_number' => $item->order?->number,
                    'ordered_at' => $item->order?->placed_at,
                ])
                ->values()
                ->all();
        }

        $personalized = collect();
        if ($customer) {
            $personalized = $recommendationService
                ->personalized($customer, 6)
                ->map(fn (Product $p) => $this->transformProduct($p));
        }

        $promotions = app(PromotionHomepageService::class)->getPromotionsForTargets(
            [$product->id],
            [$product->category_id]
        );

        return Inertia::render('Products/Show', [
            'product' => $this->transformProduct($product, true),
            'currency' => $product->currency ?? 'USD',
            'promotions' => $promotions,
            'reviews' => $reviews->map(fn (ProductReview $review) => [
                'id' => $review->id,
                'rating' => $review->rating,
                'title' => $review->title,
                'body' => $review->body,
                'images' => $review->images ?? [],
                'verified_purchase' => (bool) $review->verified_purchase,
                'helpful_count' => $review->helpful_count ?? 0,
                'created_at' => $review->created_at,
                'author' => $review->customer?->name ?? 'Verified buyer',
            ]),
            'reviewSummary' => [
                'count' => $reviewCount,
                'average' => $reviewAverage,
                'breakdown' => $reviewBreakdown,
            ],
            'reviewHighlights' => $reviewHighlights,
            'relatedProducts' => $related,
            'personalizedProducts' => $personalized,
            'reviewableItems' => $reviewableItems,
        ]);
    }

}
