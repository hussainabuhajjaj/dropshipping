<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\CartResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Domain\Products\Models\ProductVariant;
use App\Models\Coupon;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Services\Api\ApiException;
use App\Services\AbandonedCartService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Carbon;

class CartController extends Controller
{
    public function index(): Response
    {
        $cart = $this->getCart();
        $cart_items = $this->cart();
        $coupon = session('cart_coupon');
        $subtotal = $cart->subTotal();
        $discount = $cart->discount($coupon);

        $shipping = $cart->calculateShippingFees();

        // Get applied promotions (not just coupon)
        $promotionEngine = app(\App\Services\Promotions\PromotionEngine::class);
        $cartContext = [
            'lines' => $cart_items,
            'subtotal' => $subtotal,
            'user_id' => auth('customer')->id(),
        ];
        $appliedPromotions = $promotionEngine->getApplicablePromotions($cartContext)->map(function ($promo) {
            return [
                'id' => $promo->id,
                'name' => $promo->name,
                'description' => $promo->description,
                'type' => $promo->type,
                'value_type' => $promo->value_type,
                'value' => $promo->value,
                'start_at' => $promo->start_at,
                'end_at' => $promo->end_at,
                'targets' => $promo->targets,
                'conditions' => $promo->conditions,
            ];
        })->values()->all();

        return Inertia::render('Cart/Index', [
            'lines' => (CartResource::collection($cart_items))->jsonSerialize(),
            'currency' => $cart[0]['currency'] ?? 'USD',
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'discount' => $discount,
            'coupon' => $coupon,
            'appliedPromotions' => $appliedPromotions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $product = Product::query()
            ->where('is_active', true)
            ->with(['images', 'variants', 'defaultFulfillmentProvider'])
            ->findOrFail($data['product_id']);

        $variant = null;
        if (!empty($data['variant_id'])) {
            $variant = $product->variants->firstWhere('id', (int)$data['variant_id']);
        }


        $cart = $this->cart();

        $existing = $cart->where('product_id', $product->id)
            ->when(isset($variant), function ($query) use ($variant) {
                return $query->where('variant_id', $variant->id);
            })->first();


        $incomingQty = (int)($data['quantity'] ?? 1);

        if ($existing) {
            $newQty = $existing['quantity'] + $incomingQty;
            if (!$this->hasStock($existing->toArray(), $newQty, $variant)) {
                return back()->withErrors(['cart' => 'Insufficient stock for this item.']);
            }

            $existing->update(['quantity' => $newQty]);
        } else {
            $line = $this->buildLine($product, $variant, $incomingQty);
            if (!$this->hasStock($line, $incomingQty, $variant)) {
                return back()->withErrors(['cart' => 'Insufficient stock for this item.']);
            }
            CartItem::query()->create($line);
        }

        $this->captureAbandonedCart($cart);

        return back()->with('cart_notice', 'Added to cart');
    }

    public function destroy(string $lineId): RedirectResponse
    {
        $cart = $this->cart()->where('id', $lineId)->first();
        if (isset($cart)) {
            $cart->delete();
            $this->captureAbandonedCart($cart);
        }
        return back();
    }

    public function update(string $lineId, Request $request): RedirectResponse
    {
        $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $newQty = (int)$request->input('quantity');

        $cartItems = $this->cart();
        $cart = $cartItems->where('id', $lineId)->first();

        if ($cart) {
            $variant = $cart->variant;
            if (!$this->hasStock($cart->toArray(), $newQty, $variant)) {
                return back()->withErrors(['cart' => 'Insufficient stock for this item.']);
            }

            $cart->update(['quantity' => $newQty]);
        }
        $this->captureAbandonedCart($cart);
        return back();
    }

    public function getCart()
    {
        $cart = Cart::query()->where('user_id', auth('web')->id())
            ->orWhere('session_id', session()->id())
            ->first();
        if (!$cart) {
            return Cart::createCart();
        }
        return $cart;
    }

    private function cart()
    {
        $cart = $this->getCart();

        return CartItem::query()->where('cart_id', @$cart?->id)
            ->with(['product', 'variant'])
            ->get();
    }

    private function subtotal($carts): float
    {
        return $carts->reduce(function ($sub_total, $item) {
            return $sub_total + ((float)$item->getSinglePrice() * (int)$item['quantity']);
        }, 0.0);
    }

    /**
     * Capture abandoned cart from guest checkout (AJAX).
     */
    public function abandon(Request $request)
    {
        $data = $request->validate([
            'cart' => 'required|array',
            'email' => 'nullable|email',
        ]);

        app(\App\Services\AbandonedCartService::class)->capture(
            $data['cart'],
            $data['email'] ?? null,
            null
        );

        return response()->json(['status' => 'ok']);
    }


    private function buildLine(Product $product, ?ProductVariant $variant, int $quantity): array
    {
        $cart = $this->getCart();
        $selectedVariant = $variant ?? $product->variants->first();
        return [
            'cart_id' => @$cart->id,
            'product_id' => $product->id,
            'variant_id' => $selectedVariant?->id,
            'fulfillment_provider_id' => $product->default_fulfillment_provider_id,
            'quantity' => $quantity,
            'stock_on_hand' => $selectedVariant?->stock_on_hand ?? $product->stock_on_hand,

//            'id' => Str::uuid()->toString(),
//            'name' => $product->name,
//            'variant' => $selectedVariant?->title,
//            'price' => (float)($selectedVariant?->price ?? $product->selling_price ?? 0),
//            'currency' => $selectedVariant?->currency ?? $product->currency ?? 'USD',
//            'media' => $product->images?->sortBy('position')->pluck('url')->values()->all() ?? [],
//            'sku' => $selectedVariant?->sku,
//            'cj_pid' => $product->attributes['cj_pid'] ?? null,
//            'cj_vid' => $selectedVariant?->metadata['cj_vid'] ?? null,
        ];
    }

    public function applyCoupon(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:255'],
        ]);

        $now = Carbon::now();
        $coupon = Coupon::query()
            ->where('code', $data['code'])
            ->where('is_active', true)
            ->where(fn($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->first();

        if (!$coupon) {
            return back()->withErrors(['code' => 'Coupon not found or inactive.'])->withInput();
        }

        session(['cart_coupon' => [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'type' => $coupon->type,
            'amount' => $coupon->amount,
            'min_order_total' => $coupon->min_order_total,
            'description' => $coupon->description,
        ]]);

        $this->captureAbandonedCart($this->cart());

        return back()->with('cart_notice', 'Coupon applied.');
    }

    public function removeCoupon(): RedirectResponse
    {
        session()->forget('cart_coupon');
        $this->captureAbandonedCart($this->cart());
        return back()->with('cart_notice', 'Coupon removed.');
    }

    private function captureAbandonedCart($cart): void
    {
//        if (empty($cart)) {
//            return;
//        }
//
//        app(AbandonedCartService::class)->capture(
//            $cart,
//            Auth::guard('customer')->user()?->email,
//            Auth::guard('customer')->id()
//        );
    }

    private function hasCjStock(array $line, int $desiredQty): bool
    {
        // Prefer local stock snapshot if available before hitting CJ APIs
        if (array_key_exists('stock_on_hand', $line) && is_numeric($line['stock_on_hand'])) {
            return (int)$line['stock_on_hand'] >= $desiredQty;
        }

        $client = app(CJDropshippingClient::class);

        try {
            if ($line['cj_vid'] ?? false) {
                $resp = $client->getStockByVid((string)$line['cj_vid']);
            } elseif ($line['sku'] ?? false) {
                $resp = $client->getStockBySku((string)$line['sku']);
            } elseif ($line['cj_pid'] ?? false) {
                $resp = $client->getStockByPid((string)$line['cj_pid']);
            } else {
                return true;
            }

            return $this->sumStorage($resp->data ?? null) >= $desiredQty;
        } catch (ApiException $exception) {
            Log::warning('CJ stock check failed', ['error' => $exception->getMessage(), 'line' => $line['id'] ?? null]);
            return true; // allow on API failure
        } catch (\Throwable $exception) {
            Log::error('CJ stock check failed', ['error' => $exception->getMessage(), 'line' => $line['id'] ?? null]);
            return true; // allow on error
        }
    }

    private function sumStorage(mixed $payload): int
    {
        $total = 0;

        $add = function ($value) use (&$total) {
            if (is_numeric($value)) {
                $total += (int)$value;
            }
        };

        if (is_numeric($payload)) {
            $add($payload);
            return $total;
        }

        if (is_array($payload)) {
            if (array_key_exists('storageNum', $payload)) {
                $add($payload['storageNum']);
            }

            foreach ($payload as $entry) {
                if (is_array($entry) && array_key_exists('storageNum', $entry)) {
                    $add($entry['storageNum']);
                } elseif (is_array($entry)) {
                    foreach ($entry as $deep) {
                        if (is_array($deep) && array_key_exists('storageNum', $deep)) {
                            $add($deep['storageNum']);
                        }
                    }
                }
            }
        }

        return $total;
    }

    private function hasStock(array $line, int $desiredQty, ?ProductVariant $variant = null): bool
    {
        // 1. Check local stock_on_hand first (variant or product level)
        if (array_key_exists('stock_on_hand', $line) && is_numeric($line['stock_on_hand'])) {
            $available = (int)$line['stock_on_hand'];
            if ($available < $desiredQty) {
                return false;
            }
            return true; // local stock sufficient
        } elseif ($variant && $variant->stock_on_hand !== null) {
            if ($variant->stock_on_hand < $desiredQty) {
                return false;
            }
            return true;
        }

        // 2. Fallback to live CJ API check if no local stock data
        return $this->hasCjStock($line, $desiredQty);
    }

}

