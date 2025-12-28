<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

        $orderItem = OrderItem::query()
            ->with(['productVariant.product', 'review', 'order'])
            ->where('id', $data['order_item_id'])
            ->where('fulfillment_status', 'fulfilled')
            ->whereHas('shipments', function ($builder) {
                $builder->whereNotNull('delivered_at');
            })
            ->whereHas('order', function ($builder) use ($customer) {
                $builder
                    ->where('customer_id', $customer->id)
                    ->where('status', 'fulfilled');
            })
            ->firstOrFail();

        if ($orderItem->review) {
            return back()->withErrors([
                'order_item_id' => 'Review already submitted for this item.',
            ]);
        }

        $reviewProductId = $orderItem->productVariant?->product_id;
        if ($reviewProductId !== $product->id) {
            return back()->withErrors([
                'order_item_id' => 'This order item does not match the selected product.',
            ]);
        }

        $settings = SiteSetting::query()->first();
        $status = $settings?->auto_approve_reviews ? 'approved' : 'pending';

        ProductReview::create([
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'order_id' => $orderItem->order_id,
            'order_item_id' => $orderItem->id,
            'rating' => (int) $data['rating'],
            'title' => $data['title'],
            'body' => $data['body'],
            'status' => $status,
            'images' => $this->processImages($request),
            'verified_purchase' => true,
            'helpful_count' => 0,
        ]);

        return back()->with(
            'review_notice',
            $status === 'approved'
                ? 'Thanks for your review. It is now live.'
                : 'Thanks for your review. It will appear after approval.'
        );
    }

    private function processImages(Request $request): ?array
    {
        if (! $request->hasFile('images')) {
            return null;
        }

        $images = [];
        foreach ($request->file('images', []) as $image) {
            if ($image->isValid()) {
                $path = $image->store('reviews', 'public');
                $images[] = asset("storage/{$path}");
            }
        }

        return empty($images) ? null : $images;
    }
}
