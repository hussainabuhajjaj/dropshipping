<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\TransformsProducts;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class WishlistController extends Controller
{
    use TransformsProducts;

    public function index(): Response
    {
        $ids = collect(session('wishlist', []))
            ->map(fn ($item) => (int) $item)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $products = $ids
            ? Product::query()
                ->whereIn('id', $ids)
                ->where('is_active', true)
                ->with(['images', 'variants'])
                ->get()
            : collect();

        return Inertia::render('Account/Wishlist', [
            'products' => $products->map(fn (Product $product) => $this->transformProduct($product)),
            'currency' => 'USD',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        $wishlist = collect(session('wishlist', []))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $message = 'Already in wishlist.';
        if (! $wishlist->contains($data['product_id'])) {
            $wishlist->push($data['product_id']);
            session(['wishlist' => $wishlist->values()->all()]);
            $message = 'Added to wishlist.';
        }

        session()->flash('wishlist_notice', $message);

        return back(303);
    }

    public function destroy(Product $product): RedirectResponse
    {
        $wishlist = collect(session('wishlist', []))
            ->reject(fn ($id) => (int) $id === $product->id)
            ->values()
            ->all();

        session(['wishlist' => $wishlist]);
        session()->flash('wishlist_notice', 'Removed from wishlist.');

        return back(303);
    }
}
