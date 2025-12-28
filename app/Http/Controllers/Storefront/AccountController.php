<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Common\Models\Address;
use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\GiftCard;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $addresses = Address::query()
            ->where('customer_id', $user->id)
            ->latest()
            ->get()
            ->map(fn (Address $address) => [
                'id' => $address->id,
                'name' => $address->name,
                'phone' => $address->phone,
                'line1' => $address->line1,
                'line2' => $address->line2,
                'city' => $address->city,
                'state' => $address->state,
                'postal_code' => $address->postal_code,
                'country' => $address->country,
                'type' => $address->type,
                'is_default' => $address->is_default,
            ]);

        $paymentMethods = PaymentMethod::query()
            ->where('customer_id', $user->id)
            ->orderByDesc('is_default')
            ->latest()
            ->get()
            ->map(fn (PaymentMethod $method) => [
                'id' => $method->id,
                'provider' => $method->provider,
                'brand' => $method->brand,
                'last4' => $method->last4,
                'exp_month' => $method->exp_month,
                'exp_year' => $method->exp_year,
                'nickname' => $method->nickname,
                'is_default' => $method->is_default,
            ]);

        $orders = Order::query()
            ->where('customer_id', $user->id)
            ->latest('placed_at')
            ->take(10)
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'grand_total' => $order->grand_total,
                'currency' => $order->currency,
                'placed_at' => $order->placed_at,
                'email' => $order->email,
            ]);

        $refunds = Payment::query()
            ->where('status', 'refunded')
            ->whereHas('order', fn ($query) => $query->where('customer_id', $user->id))
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'order_number' => $payment->order?->number,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'provider_reference' => $payment->provider_reference,
                'updated_at' => $payment->updated_at,
            ]);

        $giftCards = GiftCard::query()
            ->where('customer_id', $user->id)
            ->latest()
            ->get()
            ->map(fn (GiftCard $card) => [
                'id' => $card->id,
                'code' => $card->code,
                'balance' => $card->balance,
                'currency' => $card->currency,
                'status' => $card->status,
                'expires_at' => $card->expires_at,
            ]);

        $savedCouponIds = CouponRedemption::query()
            ->where('customer_id', $user->id)
            ->pluck('coupon_id')
            ->all();

        $availableCoupons = Coupon::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $now = Carbon::now();
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) {
                $now = Carbon::now();
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->when($savedCouponIds, fn ($query) => $query->whereNotIn('id', $savedCouponIds))
            ->orderBy('code')
            ->get()
            ->map(fn (Coupon $coupon) => $this->transformCoupon($coupon));

        $savedCoupons = CouponRedemption::query()
            ->with('coupon')
            ->where('customer_id', $user->id)
            ->latest()
            ->get()
            ->map(function (CouponRedemption $redemption) {
                return [
                    'id' => $redemption->id,
                    'status' => $redemption->status,
                    'redeemed_at' => $redemption->redeemed_at,
                    'coupon' => $redemption->coupon ? $this->transformCoupon($redemption->coupon) : null,
                ];
            });

        return Inertia::render('Account/Index', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
            'addresses' => $addresses,
            'paymentMethods' => $paymentMethods,
            'orders' => $orders,
            'refunds' => $refunds,
            'giftCards' => $giftCards,
            'savedCoupons' => $savedCoupons,
            'availableCoupons' => $availableCoupons,
        ]);
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
            'line1' => 'required|string|max:255',
            'line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:2',
            'type' => 'nullable|string|max:20',
            'is_default' => 'nullable|boolean',
        ]);

        $data['customer_id'] = $request->user()->id;
        $data['country'] = $data['country'] ?: 'CI';
        $data['type'] = $data['type'] ?: 'shipping';
        $data['is_default'] = (bool) ($data['is_default'] ?? false);

        if (! Address::query()->where('customer_id', $data['customer_id'])->exists()) {
            $data['is_default'] = true;
        }

        if ($data['is_default']) {
            Address::query()
                ->where('customer_id', $data['customer_id'])
                ->update(['is_default' => false]);
        }

        Address::create($data);

        return Redirect::back();
    }

    public function updateAddress(Request $request, Address $address): RedirectResponse
    {
        if ($address->customer_id !== $request->user()->id) {
            abort(403);
        }

        $data = $request->validate([
            'name' => 'sometimes|nullable|string|max:255',
            'phone' => 'sometimes|nullable|string|max:30',
            'line1' => 'sometimes|required|string|max:255',
            'line2' => 'sometimes|nullable|string|max:255',
            'city' => 'sometimes|nullable|string|max:255',
            'state' => 'sometimes|nullable|string|max:255',
            'postal_code' => 'sometimes|nullable|string|max:20',
            'country' => 'sometimes|nullable|string|max:2',
            'type' => 'sometimes|nullable|string|max:20',
            'is_default' => 'sometimes|boolean',
        ]);

        $address->update($data);

        if (! empty($data['is_default'])) {
            Address::query()
                ->where('customer_id', $request->user()->id)
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);
        }

        return Redirect::back();
    }

    public function destroyAddress(Request $request, Address $address): RedirectResponse
    {
        if ($address->customer_id !== $request->user()->id) {
            abort(403);
        }

        $address->delete();

        return Redirect::back();
    }

    public function storePaymentMethod(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider' => 'required|string|max:40',
            'brand' => 'nullable|string|max:40',
            'last4' => 'nullable|string|size:4',
            'exp_month' => 'nullable|integer|min:1|max:12',
            'exp_year' => 'nullable|integer|min:2024|max:2100',
            'nickname' => 'nullable|string|max:80',
            'is_default' => 'nullable|boolean',
        ]);

        $data['customer_id'] = $request->user()->id;
        $data['is_default'] = (bool) ($data['is_default'] ?? false);

        if ($data['is_default']) {
            PaymentMethod::query()
                ->where('customer_id', $data['customer_id'])
                ->update(['is_default' => false]);
        }

        PaymentMethod::create($data);

        return Redirect::back();
    }

    public function destroyPaymentMethod(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        if ($paymentMethod->customer_id !== $request->user()->id) {
            abort(403);
        }

        $paymentMethod->delete();

        return Redirect::back();
    }

    public function redeemGiftCard(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:255',
        ]);

        $giftCard = GiftCard::query()
            ->where('code', $data['code'])
            ->first();

        if (! $giftCard || $giftCard->status !== 'active') {
            return Redirect::back()->withErrors(['code' => 'Gift card not found or inactive.']);
        }

        if ($giftCard->expires_at && $giftCard->expires_at->isPast()) {
            $giftCard->update(['status' => 'expired']);
            return Redirect::back()->withErrors(['code' => 'Gift card expired.']);
        }

        if ($giftCard->customer_id && $giftCard->customer_id !== $request->user()->id) {
            return Redirect::back()->withErrors(['code' => 'Gift card already assigned.']);
        }

        $giftCard->update([
            'customer_id' => $request->user()->id,
            'redeemed_at' => $giftCard->redeemed_at ?? now(),
        ]);

        return Redirect::back();
    }

    public function saveCoupon(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:255',
        ]);

        $now = Carbon::now();
        $coupon = Coupon::query()
            ->where('code', $data['code'])
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->first();

        if (! $coupon) {
            return Redirect::back()->withErrors(['code' => 'Coupon not found or inactive.']);
        }

        CouponRedemption::firstOrCreate([
            'coupon_id' => $coupon->id,
            'customer_id' => $request->user()->id,
        ], [
            'status' => 'saved',
        ]);

        return Redirect::back();
    }

    public function destroyCoupon(Request $request, CouponRedemption $couponRedemption): RedirectResponse
    {
        if ($couponRedemption->customer_id !== $request->user()->id) {
            abort(403);
        }

        $couponRedemption->delete();

        return Redirect::back();
    }

    public function claimOrders(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'phone_last4' => 'sometimes|nullable|string|size:4',
        ]);

        $email = strtolower($data['email']);
        $phoneLast4 = $data['phone_last4'] ?? null;

        if ($email !== strtolower($request->user()->email)) {
            return Redirect::back()->withErrors(['email' => 'Email must match your account email.']);
        }

        $service = app(\App\Domain\Orders\Services\LinkGuestOrdersService::class);
        $linked = $service->linkByEmail($email, (int) $request->user()->id, $phoneLast4);

        if ($linked > 0) {
            return Redirect::back()->with('success', $linked . ' order' . ($linked > 1 ? 's' : '') . ' linked to your account.');
        }

        return Redirect::back()->withErrors(['email' => 'No matching guest orders found.']);
    }

    private function transformCoupon(Coupon $coupon): array
    {
        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'description' => $coupon->description,
            'type' => $coupon->type,
            'amount' => $coupon->amount,
            'min_order_total' => $coupon->min_order_total,
            'starts_at' => $coupon->starts_at,
            'ends_at' => $coupon->ends_at,
        ];
    }

    public function addresses(Request $request): Response
    {
        $user = $request->user();
        $addresses = Address::query()
            ->where('customer_id', $user->id)
            ->latest()
            ->get()
            ->map(fn (Address $address) => [
                'id' => $address->id,
                'name' => $address->name,
                'phone' => $address->phone,
                'line1' => $address->line1,
                'line2' => $address->line2,
                'city' => $address->city,
                'state' => $address->state,
                'postal_code' => $address->postal_code,
                'country' => $address->country,
                'type' => $address->type,
                'is_default' => $address->is_default,
            ]);

        return Inertia::render('Account/Addresses', [
            'addresses' => $addresses,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function orders(Request $request): Response
    {
        $user = $request->user();
        $orders = Order::query()
            ->where('customer_id', $user->id)
            ->latest('placed_at')
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'grand_total' => $order->grand_total,
                'currency' => $order->currency,
                'placed_at' => $order->placed_at,
                'email' => $order->email,
            ]);

        return Inertia::render('Account/Orders', [
            'orders' => $orders,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function payments(Request $request): Response
    {
        $user = $request->user();
        $paymentMethods = PaymentMethod::query()
            ->where('customer_id', $user->id)
            ->orderByDesc('is_default')
            ->latest()
            ->get()
            ->map(fn (PaymentMethod $method) => [
                'id' => $method->id,
                'provider' => $method->provider,
                'brand' => $method->brand,
                'last4' => $method->last4,
                'exp_month' => $method->exp_month,
                'exp_year' => $method->exp_year,
                'nickname' => $method->nickname,
                'is_default' => $method->is_default,
            ]);

        return Inertia::render('Account/Payments', [
            'paymentMethods' => $paymentMethods,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function refunds(Request $request): Response
    {
        $user = $request->user();
        $refunds = Payment::query()
            ->where('status', 'refunded')
            ->whereHas('order', fn ($query) => $query->where('customer_id', $user->id))
            ->latest()
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'order_number' => $payment->order?->number,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'provider_reference' => $payment->provider_reference,
                'updated_at' => $payment->updated_at,
            ]);

        return Inertia::render('Account/Refunds', [
            'refunds' => $refunds,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function wallet(Request $request): Response
    {
        $user = $request->user();
        $giftCards = GiftCard::query()
            ->where('customer_id', $user->id)
            ->latest()
            ->get()
            ->map(fn (GiftCard $card) => [
                'id' => $card->id,
                'code' => $card->code,
                'balance' => $card->balance,
                'currency' => $card->currency,
                'status' => $card->status,
                'expires_at' => $card->expires_at,
            ]);

        $savedCouponIds = CouponRedemption::query()
            ->where('customer_id', $user->id)
            ->pluck('coupon_id')
            ->all();

        $availableCoupons = Coupon::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $now = Carbon::now();
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) {
                $now = Carbon::now();
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->when($savedCouponIds, fn ($query) => $query->whereNotIn('id', $savedCouponIds))
            ->orderBy('code')
            ->get()
            ->map(fn (Coupon $coupon) => $this->transformCoupon($coupon));

        $savedCoupons = CouponRedemption::query()
            ->with('coupon')
            ->where('customer_id', $user->id)
            ->latest()
            ->get()
            ->map(function (CouponRedemption $redemption) {
                return [
                    'id' => $redemption->id,
                    'status' => $redemption->status,
                    'redeemed_at' => $redemption->redeemed_at,
                    'coupon' => $redemption->coupon ? $this->transformCoupon($redemption->coupon) : null,
                ];
            });

        return Inertia::render('Account/Wallet', [
            'giftCards' => $giftCards,
            'savedCoupons' => $savedCoupons,
            'availableCoupons' => $availableCoupons,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function notifications(Request $request): Response
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->take(50)
            ->get()
            ->map(function ($notification) {
                $data = $notification->data ?? [];

                return [
                    'id' => $notification->id,
                    'title' => $data['title'] ?? $this->notificationTitle($notification->type, $data),
                    'body' => $data['body'] ?? $this->notificationBody($notification->type, $data),
                    'action_url' => $data['action_url'] ?? $data['tracking_url'] ?? $data['order_url'] ?? $data['admin_url'] ?? null,
                    'action_label' => $data['action_label'] ?? null,
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                ];
            });

        return Inertia::render('Account/Notifications', [
            'notifications' => $notifications,
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markNotificationRead(Request $request, string $notificationId): RedirectResponse
    {
        $notification = $request->user()
            ->notifications()
            ->whereKey($notificationId)
            ->firstOrFail();

        $notification->markAsRead();

        return Redirect::back();
    }

    public function markAllNotificationsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return Redirect::back();
    }

    private function notificationTitle(string $type, array $data): string
    {
        if (str_contains($type, 'OrderConfirmedNotification')) {
            return "Order #{$data['order_number']} confirmed";
        }
        if (str_contains($type, 'DeliveryConfirmedNotification')) {
            return "Order #{$data['order_number']} delivered";
        }
        if (str_contains($type, 'ShippingDelayNotification')) {
            return "Delay on order #{$data['order_number']}";
        }
        if (str_contains($type, 'CustomsInfoNotification')) {
            return "Customs update for #{$data['order_number']}";
        }
        if (str_contains($type, 'RefundProcessedNotification')) {
            return "Refund processed for #{$data['order_number']}";
        }
        if (str_contains($type, 'CustomerShipmentNotification')) {
            return "Shipment update for #{$data['order_number']}";
        }

        return $data['title'] ?? 'Notification';
    }

    private function notificationBody(string $type, array $data): string
    {
        if (str_contains($type, 'OrderConfirmedNotification')) {
            return "Total: {$data['currency']} {$data['total']}";
        }
        if (str_contains($type, 'DeliveryConfirmedNotification')) {
            return $data['delivered_at'] ? "Delivered at {$data['delivered_at']}." : 'Delivery confirmed.';
        }
        if (str_contains($type, 'ShippingDelayNotification')) {
            return trim(implode(' ', array_filter([
                $data['reason'] ?? null,
                $data['eta'] ? "ETA: {$data['eta']}" : null,
            ]))) ?: 'Shipping delay reported.';
        }
        if (str_contains($type, 'CustomsInfoNotification')) {
            return $data['note'] ?? 'Your shipment is being processed by customs.';
        }
        if (str_contains($type, 'RefundProcessedNotification')) {
            return "Refund {$data['currency']} {$data['amount']}" . (! empty($data['reason']) ? " · {$data['reason']}" : '');
        }
        if (str_contains($type, 'CustomerShipmentNotification')) {
            $tracking = $data['tracking_number'] ?? null;
            return $tracking ? "Tracking: {$tracking}" : 'Shipment update received.';
        }

        return $data['body'] ?? 'You have a new notification.';
    }
}
