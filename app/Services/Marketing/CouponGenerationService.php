<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Models\Coupon;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Str;

class CouponGenerationService
{
    public function generateForSubscriber(NewsletterSubscriber $subscriber, float $discountPercent = 10): Coupon
    {
        $code = 'WELCOME' . strtoupper(Str::random(6));

        $coupon = Coupon::query()->create([
            'code' => $code,
            'description' => "Welcome discount for {$subscriber->email}",
            'type' => 'percentage',
            'amount' => $discountPercent,
            'max_uses' => 1,
            'uses' => 0,
            'is_active' => true,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'is_one_time_per_customer' => true,
            'applicable_to' => 'all',
            'meta' => [
                'generated_for' => 'newsletter_signup',
                'subscriber_id' => $subscriber->id,
                'subscriber_email' => $subscriber->email,
            ],
        ]);

        $subscriber->update(['meta' => array_merge($subscriber->meta ?? [], [
            'welcome_coupon_id' => $coupon->id,
            'welcome_coupon_code' => $code,
        ])]);

        return $coupon;
    }

    public function generateForAbandonedCart(string $email, int $discountPercent = 10): Coupon
    {
        $code = 'CART' . strtoupper(Str::random(8));

        return Coupon::query()->create([
            'code' => $code,
            'description' => "Abandoned cart recovery for {$email}",
            'type' => 'percentage',
            'amount' => $discountPercent,
            'max_uses' => 1,
            'uses' => 0,
            'is_active' => true,
            'starts_at' => now(),
            'ends_at' => now()->addDays(7),
            'is_one_time_per_customer' => true,
            'applicable_to' => 'all',
            'meta' => [
                'generated_for' => 'abandoned_cart',
                'subscriber_email' => $email,
            ],
        ]);
    }
}