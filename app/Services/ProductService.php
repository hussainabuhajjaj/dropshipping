<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\DatabaseManager as DBManager;
use Illuminate\Http\Request;

class ProductService
{
    public function __construct(private DBManager $db) {}

    /**
     * Build the index query with filters, sorting and pagination.
     */
    public function paginate(Request $request, int $maxPerPage = 100): LengthAwarePaginator
    {
        $query = Product::with(['category', 'images']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($request->boolean('in_stock')) {
            $query->where('stock', '>', 0);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('featured')) {
            $query->where('featured', $request->boolean('featured'));
        }

        if ($minPrice = $request->input('min_price')) {
            $query->where('price', '>=', $minPrice);
        }
        if ($maxPrice = $request->input('max_price')) {
            $query->where('price', '<=', $maxPrice);
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        if (in_array($sortBy, ['name', 'price', 'stock', 'created_at', 'views_count', 'sales_count'])) {
            $query->orderBy($sortBy, $sortDirection);
        }

        $perPage = min((int) $request->input('per_page', 15), $maxPerPage);

        return $query->paginate($perPage);
    }

    /**
     * Create a product with optional images.
     */
    public function create(array $data, ?array $images = null): Product
    {
        return $this->db->transaction(function () use ($data, $images) {
            $product = Product::create([
                'name' => $data['name'] ?? null,
                'description' => $data['description'] ?? null,
                'short_description' => $data['short_description'] ?? null,
                'sku' => $data['sku'] ?? null,
                'price' => $data['price'] ?? null,
                'cost_price' => $data['cost_price'] ?? null,
                'compare_at_price' => $data['compare_at_price'] ?? null,
                'stock' => $data['stock'] ?? 0,
                'category_id' => $data['category_id'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'featured' => $data['featured'] ?? false,
            ]);

            if ($images) {
                foreach ($images as $index => $imageData) {
                    $product->images()->create([
                        'url' => $imageData['url'],
                        'alt_text' => $imageData['alt_text'] ?? $product->name,
                        'sort_order' => $index,
                    ]);
                }
            }

            return $product->load(['category', 'images']);
        });
    }

    /**
     * Update a product and replace images if provided.
     */
    public function update(Product $product, array $data, ?array $images = null): Product
    {
        return $this->db->transaction(function () use ($product, $data, $images) {
            $product->update([
                'name' => $data['name'] ?? $product->name,
                'description' => $data['description'] ?? $product->description,
                'short_description' => $data['short_description'] ?? $product->short_description,
                'sku' => $data['sku'] ?? $product->sku,
                'price' => $data['price'] ?? $product->price,
                'cost_price' => $data['cost_price'] ?? $product->cost_price,
                'compare_at_price' => $data['compare_at_price'] ?? $product->compare_at_price,
                'stock' => $data['stock'] ?? $product->stock,
                'category_id' => $data['category_id'] ?? $product->category_id,
                'is_active' => $data['is_active'] ?? $product->is_active,
                'featured' => $data['featured'] ?? $product->featured,
            ]);

            if ($images !== null) {
                $product->images()->delete();
                foreach ($images as $index => $imageData) {
                    $product->images()->create([
                        'url' => $imageData['url'],
                        'alt_text' => $imageData['alt_text'] ?? $product->name,
                        'sort_order' => $index,
                    ]);
                }
            }

            return $product->load(['category', 'images']);
        });
    }

    /**
     * Delete a product.
     */
    public function delete(Product $product): void
    {
        $product->delete();
    }
}
