<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\TransformsProducts;
use App\Domain\Products\Models\Category;
use App\Models\Product;
use App\Services\Storefront\ProductMetaExtractor;
use App\Services\Promotions\PromotionHomepageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    use TransformsProducts;

    public function show(Request $request, Category $category, ProductMetaExtractor $metaExtractor): Response
    {
        $locale = app()->getLocale();
        $category->loadMissing([
            'translations' => fn ($q) => $q->where('locale', $locale),
            'children.translations' => fn ($q) => $q->where('locale', $locale),
            'children' => fn ($q) => $q->where('is_active', true)->withCount('products'),
        ]);
        $category->increment('view_count');

        $perPage = 18;
        $filters = $this->getFilters($request);

        // Get all descendant category IDs (including self)
        $categoryIds = $this->getDescendantCategoryIds($category);

        $productsForMeta = Product::query()
            ->where('is_active', true)
            ->whereIn('category_id', $categoryIds);

        $meta = $metaExtractor->extractFromQuery($productsForMeta);

        $attributeDefs = collect($meta['attributeDefs'])
            ->reject(fn ($attr) => in_array($attr['key'], [
                'brand',
                'cj_pid',
                'cj_last_payload',
                'cj_last_changed_fields',
                'cj_payload',
                'cjpid',
                'cj',
            ], true))
            ->values()
            ->all();

        // Remove brand and CJ fields from filters
        $filters = collect($filters)
            ->except([
                'brand',
                'cj_pid',
                'cj_last_payload',
                'cj_last_changed_fields',
                'cj_payload',
                'cjpid',
                'cj',
            ])
            ->all();

        foreach ($attributeDefs as $attr) {
            $filters[$attr['key']] = $request->query($attr['key']);
        }

        // IMPORTANT: pass $categoryIds (descendants), not just $category->id
        $productQuery = $this->buildProductQuery($filters, $attributeDefs, $categoryIds, $locale);

        $products = $productQuery
            ->paginate($perPage)
            ->appends($filters)
            ->through(fn (Product $product) => $this->transformProduct($product));

        $productIds = $products->getCollection()->pluck('id')->all();
        $categoryIds = $products->getCollection()->pluck('category_id')->filter()->unique()->values()->all();
        $promotions = app(PromotionHomepageService::class)->getPromotionsForPlacement('category', $productIds, $categoryIds);

        $heroImage = $category->hero_image;
        if ($heroImage && !str_starts_with($heroImage, 'http://') && !str_starts_with($heroImage, 'https://')) {
            $heroImage = url(Storage::url($heroImage));
        }

        return Inertia::render('Categories/Show', [
            'category' => [
                'id' => $category->id,
                'name' => $category->translatedValue('name', $locale),
                'slug' => $category->slug,
                'description' => $category->translatedValue('description', $locale),
                'hero_title' => $category->translatedValue('hero_title', $locale),
                'hero_subtitle' => $category->translatedValue('hero_subtitle', $locale),
                'hero_image' => $heroImage,
                'hero_cta_label' => $category->translatedValue('hero_cta_label', $locale),
                'hero_cta_link' => $category->hero_cta_link,
                'meta_title' => $category->translatedValue('meta_title', $locale),
                'meta_description' => $category->translatedValue('meta_description', $locale),
                'children' => $category->children
                    ->map(fn (Category $child) => [
                        'id' => $child->id,
                        'name' => $child->translatedValue('name', $locale),
                'slug' => $child->slug,
                'image' => ($child->image ?? $child->hero_image)
                    ? (str_starts_with((string) ($child->image ?? $child->hero_image), 'http://') || str_starts_with((string) ($child->image ?? $child->hero_image), 'https://')
                        ? ($child->image ?? $child->hero_image)
                        : url(Storage::url($child->image ?? $child->hero_image)))
                    : null,
                'product_count' => (int) ($child->products_count ?? 0),
            ])
                    ->values()
                    ->all(),
            ],
            'subcategories' => $category->children
                ->map(fn (Category $child) => [
                    'id' => $child->id,
                    'name' => $child->translatedValue('name', $locale),
                    'slug' => $child->slug,
                    'image' => ($child->image ?? $child->hero_image)
                        ? (str_starts_with((string) ($child->image ?? $child->hero_image), 'http://') || str_starts_with((string) ($child->image ?? $child->hero_image), 'https://')
                            ? ($child->image ?? $child->hero_image)
                            : url(Storage::url($child->image ?? $child->hero_image)))
                        : null,
                    'product_count' => (int) ($child->products_count ?? 0),
                ])
                ->values()
                ->all(),
            'products' => $products,
            'currency' => 'USD',
            'promotions' => $promotions,
            'filters' => $filters,
            'attributes' => $attributeDefs,
            'breadcrumbs' => [
                ['label' => 'Home', 'href' => '/'],
                ['label' => 'Products', 'href' => '/products'],
                ['label' => $category->translatedValue('name', $locale), 'href' => null],
            ],
        ]);
    }

    private function getFilters(Request $request): array
    {
        return [
            'q' => $request->query('q'),
            'min_price' => $request->query('min_price'),
            'max_price' => $request->query('max_price'),
            'rating' => $request->query('rating'),
            'in_stock' => $request->query('in_stock'),
            'categories' => array_values(array_filter((array) $request->query('categories', []))),
            'is_featured' => $request->query('is_featured'),
            'status' => $request->query('status'),
            'sort' => $request->query('sort'),
            'page' => $request->query('page', '1'),
        ];
    }

    /**
     * Get all descendant category IDs (including the given category itself)
     */
    private function getDescendantCategoryIds(Category $category): array
    {
        $ids = [$category->id];

        // Make sure children are loaded (avoid lazy-loading surprises)
        $category->loadMissing('children');

        foreach ($category->children as $child) {
            $ids = array_merge($ids, $this->getDescendantCategoryIds($child));
        }

        return array_values(array_unique($ids));
    }

    private function buildProductQuery(array $filters, array $attributeDefs, int|array $categoryIds, string $locale)
    {
        $categoryIds = is_array($categoryIds) ? $categoryIds : [$categoryIds];

        $productQuery = Product::query()
            ->where('is_active', true)
            ->whereIn('category_id', $categoryIds)
            ->with([
                'images',
                'category',
                'category.translations' => fn ($query) => $query->where('locale', $locale),
                'variants',
                'translations' => fn ($query) => $query->where('locale', $locale),
            ])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        if (! empty($filters['categories'])) {
            $selectedCategoryIds = Category::query()
                ->whereIn('id', $filters['categories'])
                ->orWhereIn('slug', $filters['categories'])
                ->pluck('id')
                ->all();

            if ($selectedCategoryIds !== []) {
                $productQuery->whereIn('category_id', $selectedCategoryIds);
            }
        }

        if (!empty($filters['q'])) {
            $q = $filters['q'];
            $productQuery->where(function ($builder) use ($q, $locale) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhereHas('translations', function ($translationBuilder) use ($q, $locale) {
                        $translationBuilder
                            ->where('locale', $locale)
                            ->where(function ($translated) use ($q) {
                                $translated
                                    ->where('name', 'like', "%{$q}%")
                                    ->orWhere('description', 'like', "%{$q}%");
                            });
                    })
                    ->orWhereHas('category', function ($catBuilder) use ($q) {
                        $catBuilder->where('name', 'like', "%{$q}%")
                            ->orWhere('slug', 'like', "%{$q}%");
                    })
                    ->orWhereHas('category.translations', function ($categoryTranslationBuilder) use ($q, $locale) {
                        $categoryTranslationBuilder
                            ->where('locale', $locale)
                            ->where('name', 'like', "%{$q}%");
                    });
            });
        }

        if ($filters['min_price'] !== null && is_numeric($filters['min_price'])) {
            $productQuery->where('selling_price', '>=', (float) $filters['min_price']);
        }

        if ($filters['max_price'] !== null && is_numeric($filters['max_price'])) {
            $productQuery->where('selling_price', '<=', (float) $filters['max_price']);
        }

        if ($filters['rating'] !== null && is_numeric($filters['rating'])) {
            $productQuery->having('reviews_avg_rating', '>=', (float) $filters['rating']);
        }

        if (!empty($filters['in_stock'])) {
            $productQuery->where('stock_on_hand', '>', 0);
        }

        if ($filters['is_featured'] !== null) {
            $val = $filters['is_featured'];
            if ($val === '1' || $val === 1 || $val === true || $val === 'true') {
                $productQuery->where('is_featured', true);
            } elseif ($val === '0' || $val === 0 || $val === false || $val === 'false') {
                $productQuery->where('is_featured', false);
            }
        }

        if (!empty($filters['status'])) {
            $productQuery->where('status', $filters['status']);
        }

        // Dynamic attribute filters
        foreach ($attributeDefs as $attr) {
            $key = $attr['key'];
            if (!empty($filters[$key])) {
                $productQuery->whereJsonContains('attributes->' . $key, $filters[$key]);
            }
        }

        // Sorting
        $sort = $filters['sort'] ?? null;
        $sortable = [
            'price_asc' => ['selling_price', 'asc'],
            'price_desc' => ['selling_price', 'desc'],
            'newest' => ['created_at', 'desc'],
            'rating' => ['reviews_avg_rating', 'desc'],
            'popularity' => ['reviews_count', 'desc'],
            'featured' => ['is_featured', 'desc'],
        ];

        if ($sort && isset($sortable[$sort])) {
            [$field, $direction] = $sortable[$sort];
            $productQuery->orderBy($field, $direction);

            if ($sort === 'featured') {
                $productQuery->orderBy('created_at', 'desc');
            }
        } else {
            $productQuery->orderBy('created_at', 'desc');
        }

        return $productQuery;
    }
}
