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
            $query->where('stock_on_hand', '>', 0);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('featured')) {
            $query->where('is_featured', $request->boolean('featured'));
        }

        if ($minPrice = $request->input('min_price')) {
            $query->where('selling_price', '>=', $minPrice);
        }
        if ($maxPrice = $request->input('max_price')) {
            $query->where('selling_price', '<=', $maxPrice);
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        if (in_array($sortBy, ['name', 'selling_price', 'stock_on_hand', 'created_at', 'views_count', 'sales_count'])) {
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
                'selling_price' => $data['selling_price'] ?? null,
                'cost_price' => $data['cost_price'] ?? null,
                'stock_on_hand' => $data['stock_on_hand'] ?? 0,
                'category_id' => $data['category_id'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'is_featured' => $data['is_featured'] ?? false,
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
                'selling_price' => $data['selling_price'] ?? $product->selling_price,
                'cost_price' => $data['cost_price'] ?? $product->cost_price,
                'stock_on_hand' => $data['stock_on_hand'] ?? $product->stock_on_hand,
                'category_id' => $data['category_id'] ?? $product->category_id,
                'is_active' => $data['is_active'] ?? $product->is_active,
                'is_featured' => $data['is_featured'] ?? $product->is_featured,
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
