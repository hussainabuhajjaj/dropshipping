<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\TransformsProducts;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    use TransformsProducts;

    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->withCount('products')
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category) => $this->mapCategoryCard($category))
            ->values()
            ->all();

        return response()->json([
            'categories' => $categories,
        ]);
    }

    public function show(Request $request, Category $category): JsonResponse
    {
        $perPage = min((int) ($request->query('per_page') ?? 18), 50);

        $productQuery = Product::query()
            ->where('is_active', true)
            ->where('category_id', $category->id)
            ->with(['images', 'category', 'variants', 'translations'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        $products = $productQuery
            ->latest()
            ->paginate($perPage);

        $products->getCollection()->transform(fn (Product $product) => $this->transformProduct($product));

        $heroImage = $category->hero_image;
        if ($heroImage && ! str_starts_with($heroImage, 'http://') && ! str_starts_with($heroImage, 'https://')) {
            $heroImage = Storage::url($heroImage);
        }

        return response()->json([
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'heroTitle' => $category->hero_title,
                'heroSubtitle' => $category->hero_subtitle,
                'heroImage' => $heroImage,
                'heroCtaLabel' => $category->hero_cta_label,
                'heroCtaLink' => $category->hero_cta_link,
                'metaTitle' => $category->meta_title,
                'metaDescription' => $category->meta_description,
            ],
            'products' => $products->items(),
            'pagination' => [
                'currentPage' => $products->currentPage(),
                'lastPage' => $products->lastPage(),
                'perPage' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    private function mapCategoryCard(Category $category): array
    {
        $image = $category->hero_image;
        if ($image && ! str_starts_with($image, 'http://') && ! str_starts_with($image, 'https://')) {
            $image = Storage::url($image);
        }

        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'count' => $category->products_count ?? 0,
            'image' => $image,
        ];
    }
}
