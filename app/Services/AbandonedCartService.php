<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AbandonedCart;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AbandonedCartService
{
    public function capture(array $cart, ?string $email = null, ?int $customerId = null): void
    {
        if (empty($cart)) {
            return;
        }

        $sessionId = session()->getId();
        $customerId = $customerId ?? Auth::guard('customer')->id();
        $email = $email ?? Auth::guard('customer')->user()?->email;

        try {
            AbandonedCart::updateOrCreate(
                ['session_id' => $sessionId],
                [
                    'customer_id' => $customerId,
                    'email' => $email,
                    'cart_data' => $cart,
                    'abandoned_at' => now(),
                    'last_activity_at' => now(),
                    'recovered_at' => null,
                    'reminder_sent_at' => null,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to capture abandoned cart', ['error' => $e->getMessage()]);
        }
    }

    public function markRecovered(?string $sessionId = null): void
    {
        $sessionId = $sessionId ?? session()->getId();
        if (! $sessionId) {
            return;
        }

        AbandonedCart::where('session_id', $sessionId)
            ->update(['recovered_at' => now()]);
    }
}
