<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\TransformsProducts;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    use TransformsProducts;

    public function show(Category $category): Response
    {
        $perPage = 18;
        $productQuery = Product::query()
            ->where('is_active', true)
            ->where('category_id', $category->id)
            ->with(['images', 'category', 'variants', 'translations'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        $products = $productQuery
            ->latest()
            ->paginate($perPage)
            ->through(fn (Product $product) => $this->transformProduct($product));

        $heroImage = $category->hero_image;
        if ($heroImage && ! str_starts_with($heroImage, 'http://') && ! str_starts_with($heroImage, 'https://')) {
            $heroImage = Storage::url($heroImage);
        }

        return Inertia::render('Categories/Show', [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'hero_title' => $category->hero_title,
                'hero_subtitle' => $category->hero_subtitle,
                'hero_image' => $heroImage,
                'hero_cta_label' => $category->hero_cta_label,
                'hero_cta_link' => $category->hero_cta_link,
                'meta_title' => $category->meta_title,
                'meta_description' => $category->meta_description,
            ],
            'products' => $products,
            'currency' => 'USD',
        ]);
    }
}
