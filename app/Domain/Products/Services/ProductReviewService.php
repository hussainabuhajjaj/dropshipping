<?php

declare(strict_types=1);

namespace App\Domain\Products\Services;

use App\Models\Customer;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\SiteSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class ProductReviewService
{
    public function createReview(Customer $customer, Product $product, array $data, array $images = []): ProductReview
    {
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
            ->first();

        if (! $orderItem) {
            throw ValidationException::withMessages([
                'order_item_id' => ['Order item is not eligible for review.'],
            ]);
        }

        if ($orderItem->review) {
            throw ValidationException::withMessages([
                'order_item_id' => ['Review already submitted for this item.'],
            ]);
        }

        $reviewProductId = $orderItem->productVariant?->product_id;
        if ($reviewProductId !== $product->id) {
            throw ValidationException::withMessages([
                'order_item_id' => ['This order item does not match the selected product.'],
            ]);
        }

        $settings = SiteSetting::query()->first();
        $status = $settings?->auto_approve_reviews ? 'approved' : 'pending';

        return ProductReview::create([
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'order_id' => $orderItem->order_id,
            'order_item_id' => $orderItem->id,
            'rating' => (int) $data['rating'],
            'title' => $data['title'] ?? null,
            'body' => $data['body'],
            'status' => $status,
            'images' => $this->processImages($images),
            'verified_purchase' => true,
            'helpful_count' => 0,
        ]);
    }

    /**
     * @param array<int, UploadedFile> $images
     */
    private function processImages(array $images = []): ?array
    {
        if ($images === []) {
            return null;
        }

        $stored = [];
        foreach ($images as $image) {
            if ($image instanceof UploadedFile && $image->isValid()) {
                $path = $image->store('reviews', 'public');
                $stored[] = asset("storage/{$path}");
            }
        }

        return $stored === [] ? null : $stored;
    }
}
