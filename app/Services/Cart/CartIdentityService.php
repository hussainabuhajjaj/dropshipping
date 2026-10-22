<?php

declare(strict_types=1);

namespace App\Services\Cart;

use App\Contracts\Cart\CartManagerContract;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Services\Analytics\VisitTrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CartIdentityService implements CartManagerContract
{
    public function resolveCart(Request $request, ?Customer $customer = null, bool $create = false): ?Cart
    {
        $customer ??= $request->user('customer') ?: auth('customer')->user();

        if ($customer) {
            $cart = Cart::query()
                ->where('user_id', $customer->id)
                ->orderByDesc('updated_at')
                ->with('items')
                ->first();

            if ($cart) {
                return $cart;
            }
        }

        [$sessionId, $visitorId] = $this->guestIdentifiers($request);

        if (! $customer && $create && $visitorId === null) {
            $visitorId = $this->ensureVisitorId($request);
        }

        $cart = Cart::query()
            ->whereNull('user_id')
            ->where(function ($query) use ($sessionId, $visitorId) {
                if ($visitorId !== null) {
                    $query->where('visitor_id', $visitorId);
                }

                if ($sessionId !== null) {
                    $method = $visitorId !== null ? 'orWhere' : 'where';
                    $query->{$method}('session_id', $sessionId);
                }
            })
            ->orderByDesc('updated_at')
            ->with('items')
            ->first();

        if ($cart) {
            if ($create && $visitorId !== null && $cart->visitor_id !== $visitorId) {
                $cart->update(['visitor_id' => $visitorId]);
            }

            return $cart;
        }

        if (! $create) {
            return null;
        }

        return Cart::query()->create([
            'user_id' => $customer?->id,
            'session_id' => $customer ? null : $sessionId,
            'visitor_id' => $customer ? null : $visitorId,
        ])->load('items');
    }

    public function mergeGuestCartIntoCustomer(Request $request, Customer $customer, ?string $legacySessionId = null): ?Cart
    {
        [$currentSessionId, $visitorId] = $this->guestIdentifiers($request);
        $sessionId = $legacySessionId ?: $currentSessionId;

        return DB::transaction(function () use ($customer, $sessionId, $visitorId) {
            $guestCart = Cart::query()
                ->whereNull('user_id')
                ->where(function ($query) use ($sessionId, $visitorId) {
                    if ($visitorId !== null) {
                        $query->where('visitor_id', $visitorId);
                    }

                    if ($sessionId !== null) {
                        $method = $visitorId !== null ? 'orWhere' : 'where';
                        $query->{$method}('session_id', $sessionId);
                    }
                })
                ->with('items')
                ->orderByDesc('updated_at')
                ->first();

            $userCart = Cart::query()->firstOrCreate(
                ['user_id' => $customer->id],
                ['session_id' => null, 'visitor_id' => null]
            );

            if (! $guestCart) {
                return $userCart->load('items');
            }

            foreach ($guestCart->items as $guestItem) {
                $existingItem = $userCart->items()
                    ->where('product_id', $guestItem->product_id)
                    ->where('variant_id', $guestItem->variant_id)
                    ->where('fulfillment_provider_id', $guestItem->fulfillment_provider_id)
                    ->first();

                if (! $existingItem) {
                    $guestItem->update(['cart_id' => $userCart->id]);
                    continue;
                }

                $mergedQuantity = $this->mergedQuantity($existingItem, $guestItem);
                $existingItem->update([
                    'quantity' => $mergedQuantity,
                    'stock_on_hand' => $guestItem->stock_on_hand ?? $existingItem->stock_on_hand,
                    'updated_at' => max($existingItem->updated_at, $guestItem->updated_at),
                ]);

                $guestItem->delete();
            }

            $guestCart->delete();

            return $userCart->load('items');
        });
    }

    public function ensureVisitorId(Request $request): string
    {
        $existing = $this->resolveVisitorId($request);
        if ($existing !== null) {
            return $existing;
        }

        $visitorId = 'gst_' . Str::lower(Str::random(24));

        Cookie::queue(cookie(
            VisitTrackingService::WEBSITE_COOKIE,
            $visitorId,
            60 * 24 * 365,
            '/',
            null,
            false,
            false,
            false,
            'lax'
        ));

        return $visitorId;
    }

    public function resolveVisitorId(Request $request): ?string
    {
        $header = trim((string) $request->header('X-Guest-Token', ''));
        if ($header !== '') {
            return $header;
        }

        $input = trim((string) $request->input('guest_token', ''));
        if ($input !== '') {
            return $input;
        }

        $cookie = trim((string) $request->cookie(VisitTrackingService::WEBSITE_COOKIE, ''));

        return $cookie !== '' ? $cookie : null;
    }

    public function guestTokenForRequest(Request $request, ?Cart $cart = null): ?string
    {
        return $this->resolveVisitorId($request)
            ?? $cart?->visitor_id
            ?? ($request->hasSession() ? $request->session()->getId() : null);
    }

    /**
     * @return array{0:?string,1:?string}
     */
    public function guestIdentifiers(Request $request): array
    {
        $sessionId = $request->hasSession() ? $request->session()->getId() : null;
        $visitorId = $this->resolveVisitorId($request);

        return [$sessionId, $visitorId];
    }

    private function mergedQuantity(CartItem $existingItem, CartItem $guestItem): int
    {
        $existingUpdatedAt = $existingItem->updated_at?->getTimestamp() ?? 0;
        $guestUpdatedAt = $guestItem->updated_at?->getTimestamp() ?? 0;

        if ($guestUpdatedAt > $existingUpdatedAt) {
            return (int) $guestItem->quantity;
        }

        if ($existingUpdatedAt > $guestUpdatedAt) {
            return (int) $existingItem->quantity;
        }

        return max((int) $existingItem->quantity, (int) $guestItem->quantity);
    }
}
