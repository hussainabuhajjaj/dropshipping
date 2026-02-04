<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Domain\Products\Services\ProductReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductReviewController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        $customer = $request->user('customer');
        if (! $customer) {
            abort(403);
        }

        $data = $request->validate([
            'order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $review = app(ProductReviewService::class)->createReview(
                $customer,
                $product,
                $data,
                $request->file('images', [])
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with(
            'review_notice',
            $review->status === 'approved'
                ? 'Thanks for your review. It is now live.'
                : 'Thanks for your review. It will appear after approval.'
        );
    }
}
