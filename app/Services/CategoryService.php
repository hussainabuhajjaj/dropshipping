<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\DatabaseManager as DBManager;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CategoryService
{
    public function __construct(private DBManager $db) {}

    /**
     * Paginate categories with optional filters.
     */
    public function paginate(Request $request, int $maxPerPage = 100): LengthAwarePaginator
    {
        $query = Category::with(['parent', 'children'])->whereNull('parent_id');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->boolean('with_products')) {
            $query->withCount('products');
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'asc');
        if (in_array($sortBy, ['name', 'slug', 'created_at'])) {
            $query->orderBy($sortBy, $sortDirection);
        }

        $perPage = min((int) $request->input('per_page', 15), $maxPerPage);

        return $query->paginate($perPage);
    }

    /**
     * Get all categories as a flat tree structure.
     */
    public function tree(Request $request): Collection
    {
        $query = Category::with('children');

        if ($request->input('search')) {
            $query->where('name', 'like', "%{$request->input('search')}%");
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return $query->whereNull('parent_id')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get a single category with its tree.
     */
    public function show(Category $category): Category
    {
        return $category->load(['parent', 'children', 'products']);
    }

    /**
     * Create a category.
     */
    public function create(array $data): Category
    {
        return $this->db->transaction(function () use ($data) {
            $category = Category::create([
                'name' => $data['name'] ?? null,
                'slug' => $data['slug'] ?? null,
                'description' => $data['description'] ?? null,
                'parent_id' => $data['parent_id'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
            ]);

            return $category->load('parent');
        });
    }

    /**
     * Update a category.
     */
    public function update(Category $category, array $data): Category
    {
        return $this->db->transaction(function () use ($category, $data) {
            // Prevent circular parent relationships
            if (isset($data['parent_id']) && $data['parent_id']) {
                $parentId = $data['parent_id'];
                if ($parentId === $category->id) {
                    throw new \InvalidArgumentException('A category cannot be its own parent.');
                }
                // Check for circular reference
                if ($this->isCircularParent($parentId, $category->id)) {
                    throw new \InvalidArgumentException('This parent relationship would create a circular reference.');
                }
            }

            $category->update([
                'name' => $data['name'] ?? $category->name,
                'slug' => $data['slug'] ?? $category->slug,
                'description' => $data['description'] ?? $category->description,
                'parent_id' => $data['parent_id'] ?? $category->parent_id,
                'is_active' => $data['is_active'] ?? $category->is_active,
                'meta_title' => $data['meta_title'] ?? $category->meta_title,
                'meta_description' => $data['meta_description'] ?? $category->meta_description,
            ]);

            return $category->load('parent');
        });
    }

    /**
     * Delete a category (soft delete by default).
     */
    public function delete(Category $category): void
    {
        $category->delete();
    }

    /**
     * Check if setting parent_id would create circular reference.
     */
    private function isCircularParent(int $parentId, int $categoryId): bool
    {
        $parent = Category::find($parentId);
        if (!$parent) {
            return false;
        }

        if ($parent->parent_id === $categoryId) {
            return true;
        }

        if ($parent->parent_id) {
            return $this->isCircularParent($parent->parent_id, $categoryId);
        }

        return false;
    }

    /**
     * Get categories by parent ID.
     */
    public function getByParent(int $parentId): Collection
    {
        return Category::where('parent_id', $parentId)
            ->orderBy('name')
            ->get();
    }

    /**
     * Recursively get all descendants.
     */
    public function getDescendants(Category $category): Collection
    {
        $descendants = collect();

        foreach ($category->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($this->getDescendants($child));
        }

        return $descendants;
    }
}
