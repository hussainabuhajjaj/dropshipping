<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AbandonedCart;
use App\Models\Coupon;
use App\Models\SiteSetting;
use App\Notifications\AbandonedCartNotification;
use App\Services\Marketing\CouponGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendAbandonedCartReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $settings = SiteSetting::query()->first();
        $config = $settings?->abandoned_cart_config ?? [];
        $enablePush = $config['enable_push'] ?? true;
        $enableWhatsApp = $config['enable_whatsapp'] ?? true;
        $enableEmail = $config['enable_email'] ?? true;

        $this->sendFirstReminders($enablePush, $enableWhatsApp, $enableEmail);
        $this->sendSecondReminders($config, $enablePush, $enableWhatsApp, $enableEmail);
        $this->sendThirdReminders($config, $enablePush, $enableWhatsApp, $enableEmail);
    }

    private function sendFirstReminders(bool $enablePush, bool $enableWhatsApp, bool $enableEmail): void
    {
        $carts = AbandonedCart::query()
            ->whereNull('recovered_at')
            ->whereNull('reminder_sent_at')
            ->where('abandoned_at', '<=', now()->subHour())
            ->where('abandoned_at', '>=', now()->subHours(2))
            ->whereNotNull('email')
            ->get();

        foreach ($carts as $cart) {
            $this->sendReminder($cart, 1, null, $enablePush, $enableWhatsApp, $enableEmail);
        }
    }

    private function sendSecondReminders(array $config, bool $enablePush, bool $enableWhatsApp, bool $enableEmail): void
    {
        $defaultCode = $config['coupon_code'] ?? 'SAVE10';

        $carts = AbandonedCart::query()
            ->whereNull('recovered_at')
            ->whereNotNull('reminder_sent_at')
            ->where('abandoned_at', '<=', now()->subDay())
            ->where('abandoned_at', '>=', now()->subDays(2))
            ->whereNotNull('email')
            ->get();

        foreach ($carts as $cart) {
            $couponCode = $this->resolveCouponCode($cart, $defaultCode);
            $this->sendReminder($cart, 2, $couponCode, $enablePush, $enableWhatsApp, $enableEmail);
        }
    }

    private function sendThirdReminders(array $config, bool $enablePush, bool $enableWhatsApp, bool $enableEmail): void
    {
        $defaultCode = $config['coupon_code'] ?? 'SAVE10';

        $carts = AbandonedCart::query()
            ->whereNull('recovered_at')
            ->where('reminder_sent_at', '<=', now()->subDay())
            ->where('abandoned_at', '<=', now()->subHours(72))
            ->where('abandoned_at', '>=', now()->subDays(4))
            ->whereNotNull('email')
            ->get();

        foreach ($carts as $cart) {
            $couponCode = $this->resolveCouponCode($cart, $defaultCode);
            $this->sendReminder($cart, 3, $couponCode, $enablePush, $enableWhatsApp, $enableEmail);
        }
    }

    private function sendReminder(
        AbandonedCart $cart,
        int $reminderNumber,
        ?string $couponCode,
        bool $enablePush,
        bool $enableWhatsApp,
        bool $enableEmail,
    ): void {
        try {
            $notification = new AbandonedCartNotification(
                cart: $cart,
                reminderNumber: $reminderNumber,
                couponCode: $couponCode ?? '',
                enablePush: $enablePush,
                enableWhatsApp: $enableWhatsApp,
                enableEmail: $enableEmail,
            );

            if ($cart->customer) {
                Notification::send($cart->customer, $notification);
            } else {
                Notification::route('mail', $cart->email)->notify($notification);
            }

            $cart->update(['reminder_sent_at' => now()]);

            Log::info("Sent abandoned cart reminder {$reminderNumber}", [
                'cart_id' => $cart->id,
                'email' => $cart->email,
                'coupon' => $couponCode,
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to send abandoned cart reminder {$reminderNumber}", [
                'cart_id' => $cart->id,
                'email' => $cart->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveCouponCode(AbandonedCart $cart, string $defaultCode): string
    {
        $meta = is_array($cart->cart_data) ? $cart->cart_data : [];

        if (isset($meta['abandoned_coupon_code'])) {
            return $meta['abandoned_coupon_code'];
        }

        $coupon = app(CouponGenerationService::class)->generateForAbandonedCart(
            email: $cart->email,
            discountPercent: 10,
        );

        $cart->update([
            'cart_data' => array_merge($meta, [
                'abandoned_coupon_id' => $coupon->id,
                'abandoned_coupon_code' => $coupon->code,
            ]),
        ]);

        Log::info('Generated dynamic coupon for abandoned cart', [
            'cart_id' => $cart->id,
            'email' => $cart->email,
            'coupon_code' => $coupon->code,
            'coupon_id' => $coupon->id,
        ]);

        return $coupon->code;
    }
}