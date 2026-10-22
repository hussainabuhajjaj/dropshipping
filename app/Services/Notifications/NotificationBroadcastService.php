<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\NotificationType;
use App\Models\Coupon;
use App\Models\Customer;
use App\Domain\Products\Models\Product;
use App\Models\Promotion;
use App\Notifications\Marketing\BroadcastPushNotification;
use Illuminate\Support\Facades\Notification;

class NotificationBroadcastService
{
    public function broadcastPromotion(Promotion $promotion): void
    {
        if (! $this->shouldNotifyPromotion($promotion)) {
            return;
        }

        $title = $promotion->localizedValue('name', null) ?? 'New promotion live';
        $body = $promotion->localizedValue('description', null) ?? 'Explore time-limited offers now.';
        $payload = [
            'promotion_id' => $promotion->id,
            'promotion_slug' => $promotion->slug,
            'promotion_url' => $this->promotionUrl($promotion),
        ];

        $this->notifyAll(NotificationType::PROMOTION, $title, $body, $payload, 'default');
    }

    public function broadcastNewProduct(Product $product): void
    {
        $title = $product->name;
        $body = $product->description ?? 'New arrival just dropped.';
        $payload = [
            'product_id' => $product->id,
            'product_slug' => $product->slug,
            'product_url' => $this->productUrl($product),
        ];

        $this->notifyAll(NotificationType::NEW_PRODUCT, $title, $body, $payload, 'default');
    }

    public function broadcastCoupon(Coupon $coupon): void
    {
        if (! $coupon->is_currently_valid) {
            return;
        }

        $title = 'New coupon available';
        $body = $coupon->description ?? "Use {$coupon->code} for savings";
        $payload = [
            'coupon_code' => $coupon->code,
            'coupon_url' => $this->couponUrl($coupon),
        ];

        $this->notifyAll(NotificationType::COUPON, $title, $body, $payload, 'default');
    }

    private function notifyAll(string $type, string $title, ?string $body, array $payload, string $channelId): void
    {
        $notification = new BroadcastPushNotification($title, $body, $type, $payload, $channelId);

        Customer::query()
            ->whereJsonContains('metadata->preferences->notifications->push', true)
            ->whereHas('expoTokens')
            ->chunk(50, fn ($customers) => Notification::send($customers, $notification));
    }

    private function shouldNotifyPromotion(Promotion $promotion): bool
    {
        if (! $promotion->is_active) {
            return false;
        }

        $startsAt = $promotion->starts_at;
        return $startsAt === null || $startsAt->isPast();
    }

    private function promotionUrl(Promotion $promotion): string
    {
        $base = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $slug = $promotion->slug ?: 'shop';
        return "{$base}/shop?promotion={$slug}";
    }

    private function productUrl(Product $product): string
    {
        $base = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $slug = $product->slug;
        return $slug ? "{$base}/products/{$slug}" : "{$base}/products";
    }

    private function couponUrl(Coupon $coupon): string
    {
        $base = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $code = $coupon->code;
        return "{$base}/shop?coupon={$code}";
    }
}
