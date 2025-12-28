<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Category\StoreCategoryRequest;
use App\Http\Requests\Api\V1\Category\UpdateCategoryRequest;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CategoryController extends ApiController
{
    public function __construct(private CategoryService $service) {}

    /**
     * Display a paginated list of root categories.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Category::class);

        $categories = $this->service->paginate($request);

        return $this->success(
            CategoryResource::collection($categories),
            'Categories retrieved successfully'
        );
    }

    /**
     * Get full category tree structure.
     */
    public function tree(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Category::class);

        $tree = $this->service->tree($request);

        return $this->success(
            CategoryResource::collection($tree),
            'Category tree retrieved successfully'
        );
    }

    /**
     * Get children of a specific category.
     */
    public function children(Category $category, Request $request): JsonResponse
    {
        Gate::authorize('view', $category);

        $children = $this->service->getByParent($category->id);

        return $this->success(
            CategoryResource::collection($children),
            'Subcategories retrieved successfully'
        );
    }

    /**
     * Display the specified category.
     */
    public function show(Category $category): JsonResponse
    {
        Gate::authorize('view', $category);

        $category = $this->service->show($category);

        return $this->success(
            new CategoryResource($category),
            'Category retrieved successfully'
        );
    }

    /**
     * Store a newly created category.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        Gate::authorize('create', Category::class);

        try {
            $category = $this->service->create($request->validated());

            return $this->created(
                new CategoryResource($category),
                'Category created successfully'
            );
        } catch (\Exception $e) {
            return $this->error(
                'Failed to create category: ' . $e->getMessage(),
                400
            );
        }
    }

    /**
     * Update the specified category.
     */
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        Gate::authorize('update', $category);

        try {
            $category = $this->service->update($category, $request->validated());

            return $this->success(
                new CategoryResource($category),
                'Category updated successfully'
            );
        } catch (\Exception $e) {
            return $this->error(
                'Failed to update category: ' . $e->getMessage(),
                400
            );
        }
    }

    /**
     * Remove the specified category.
     */
    public function destroy(Category $category): JsonResponse
    {
        Gate::authorize('delete', $category);

        try {
            $this->service->delete($category);

            return $this->deleted('Category deleted successfully');
        } catch (\Exception $e) {
            return $this->error(
                'Failed to delete category: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Helper to format pagination links.
     */
    private function paginationLinks($paginator): array
    {
        return [
            'first' => $paginator->url(1),
            'last' => $paginator->url($paginator->lastPage()),
            'prev' => $paginator->previousPageUrl(),
            'next' => $paginator->nextPageUrl(),
        ];
    }
}
