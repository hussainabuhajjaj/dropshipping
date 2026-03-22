<?php

namespace App\Models;

use App\Http\Resources\User\CartResource;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Services\Api\ApiException;
use App\Services\CartMinimumService;
use App\Services\Promotions\PromotionEngine;
use App\Services\Promotions\PromotionHomepageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Cart extends Model
{
    protected $fillable = [
        'user_id', 'session_id', 'product_id', 'fulfillment_provider_id',
        'variant_id', 'quantity', 'stock_on_hand'
    ];

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    public function shippings()
    {
        return $this->hasMany(CartShipping::class);
    }

    public static function createCart(): self
    {
        if (auth('customer')->check()) {
            return self::query()->create(['user_id' => auth('customer')->id()]);
        } else {
            return self::query()->create(['session_id' => session()->id()]);
        }
    }

    public function subTotal()
    {
        return $this->items->reduce(function ($carry, $item) {
            return $carry + $item->quantity * $item->getSinglePrice();
        }, 0);
    }

    public function discount(?array $coupon): float
    {
        if (!$coupon) {
            return 0.0;
        }

        $subtotal = $this->subTotal();
        if ($coupon['min_order_total'] && $subtotal < (float)$coupon['min_order_total']) {
            return 0.0;
        }

        if ($coupon['type'] === 'fixed') {
            return min((float)$coupon['amount'], $subtotal);
        }

        return round($subtotal * ((float)$coupon['amount'] / 100), 2);
    }

    public function calculateShippingFees()
    {
        return $this->quoteShippingForItems($this->items, true)['total'];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\CartItem>  $items
     * @return array{total: float, lines: array<int, array<string, mixed>>}
     */
    public function quoteShippingForItems(Collection $items, bool $persist = false): array
    {
        $this->removeInvalidCjItems();

        $providers = $items->groupBy('fulfillment_provider_id');
        $shippingLines = [];
        Log::info('Cart shipping calculation started', [
            'cart_id' => $this->id,
            'items' => $items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'quantity' => $item->quantity,
                    'line_total' => $item->quantity * $item->getSinglePrice(),
                ];
            })->toArray(),
            'providers' => $providers->keys()->toArray(),
        ]);

        if ($persist) {
            CartShipping::query()->where('cart_id', $this->id)->delete();
        }
        $default_warehouse = LocalWareHouse::query()->where('is_default', 1)->first();

        foreach ($providers as $provider_id => $providerItems) {
            Log::info('Evaluating provider shipping group', [
                'cart_id' => $this->id,
                'provider_id' => $provider_id,
                'line_ids' => $providerItems->pluck('id')->values()->all(),
            ]);
            if ($provider_id == 1) {
                $client = app(CJDropshippingClient::class);

                $productsForQuote = $providerItems->map(function ($item) {
                    $vid = null;
                    if (isset($item['variant_id'])) {
                        $variant = ProductVariant::query()->find($item['variant_id']);
                        $meta = is_array($variant?->metadata ?? null) ? $variant->metadata : [];
                        $vid = $meta['cj_vid'] ?? null;
                    }

                    if (! $vid) {
                        return null;
                    }

                    return [
                        'quantity' => (int) (@$item['quantity'] ?? 1),
                        'vid' => (string) $vid,
                    ];
                })->filter()->values()->all();

                if (empty($productsForQuote)) {
                    Log::warning('Skipping CJ freight quote because no valid cj_vid lines were found', [
                        'cart_id' => $this->id,
                        'provider_id' => $provider_id,
                    ]);
                    continue;
                }

                $payload = [
                    'startCountryCode' => 'CN',
                    'endCountryCode' => @$default_warehouse->country ?? 'CN',
                    'products' => $productsForQuote,
                ];
                try {
                    $result = $client->freightCalculate($payload);

                    if (isset($result->data)) {
                        $data = collect($result->data);
                        $company = $data->sortBy('logisticPrice')->first();

                        if (isset($company)) {
                            $line = [
                                'cart_id' => $this['id'],
                                'fulfillment_provider_id' => $provider_id,
                                'logistic_name' => @$company['logisticName'],
                                'logistic_price' => @$company['logisticPrice'],
                                'total_postage_fee' => @$company['totalPostageFee'],
                                'aging' => @$company['logisticAging'],
                            ];
                            $shippingLines[] = $line;
                            if ($persist) {
                                CartShipping::query()->create($line);
                            }
                            Log::info('CJ shipping quote stored', [
                                'cart_id' => $this->id,
                                'provider_id' => $provider_id,
                                'company' => [
                                    'name' => @$company['logisticName'],
                                    'price' => @$company['logisticPrice'],
                                    'postage_fee' => @$company['totalPostageFee'],
                                    'aging' => @$company['logisticAging'],
                                ],
                            ]);
                        }
                    }
                } catch (ApiException $e) {
                    $message = strtolower($e->getMessage());
                    if (str_contains($message, 'variant not found') && preg_match('/vid:\s*([0-9]+)/i', $e->getMessage(), $matches)) {
                        $missingVid = $matches[1] ?? null;
                        if ($missingVid) {
                            $this->removeItemsByCjVid((string) $missingVid);
                            Log::warning('Removed cart items with missing CJ variant during shipping calculation', [
                                'cart_id' => $this->id,
                                'missing_vid' => $missingVid,
                            ]);
                        }
                    }

                    Log::warning('CJ freight calculation failed; skipping provider shipping quote', [
                        'cart_id' => $this->id,
                        'provider_id' => $provider_id,
                        'error' => $e->getMessage(),
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Unexpected freight calculation failure; skipping provider shipping quote', [
                        'cart_id' => $this->id,
                        'provider_id' => $provider_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $total_weight = 0;
        $weight_breakdown = [];

        foreach ($items as $item) {
            $variant = $item->variant;
            $product = $item->product;
            $product_attrs = $product?->getOriginal('attributes');
            if (is_string($product_attrs)) {
                $product_attrs = json_decode($product_attrs, true);
            }
            if (!is_array($product_attrs)) {
                $product_attrs = [];
            }
            $meta = $variant?->metadata ?? [];
            if (is_string($meta)) {
                $meta = json_decode($meta, true);
            }
            if (!is_array($meta)) {
                $meta = [];
            }
            $unit_weight = 0;

            if (isset($variant)) {
                if (isset($product_attrs['cj_payload']['packingWeight'])) {
                    $pack_weight = $product_attrs['cj_payload']['packingWeight'];
                    $pack_weight = explode('-', (string) $pack_weight);
                    $unit_weight = $pack_weight[count($pack_weight) - 1] ?? 0;
                    $weight_breakdown[] = [
                        'item_id' => $item->id,
                        'source' => 'packingWeight_variant_path',
                        'weight' => $unit_weight,
                        'unit' => 'g',
                    ];
                } else if (isset($product_attrs['cj_payload']['productWeight'])) {
                    $weight = $product_attrs['cj_payload']['productWeight'];
                    $weight = explode('-', (string) $weight);
                    $unit_weight = $weight[count($weight) - 1] ?? 0;
                    $weight_breakdown[] = [
                        'item_id' => $item->id,
                        'source' => 'productWeight',
                        'weight' => $unit_weight,
                        'unit' => 'g',
                    ];
                } else if (isset($meta['cj_variant']['variantWeight'])) {
                    $unit_weight = $meta['cj_variant']['variantWeight'];
                    $weight_breakdown[] = [
                        'item_id' => $item->id,
                        'source' => 'variantWeight',
                        'weight' => $unit_weight,
                        'unit' => 'g',
                    ];
                }
            } else {
                if (isset($product_attrs['cj_payload']['packingWeight'])) {
                    $pack_weight = $product_attrs['cj_payload']['packingWeight'];
                    $pack_weight = explode('-', (string) $pack_weight);
                    $unit_weight = $pack_weight[count($pack_weight) - 1] ?? 0;
                    $weight_breakdown[] = [
                        'item_id' => $item->id,
                        'source' => 'packingWeight',
                        'weight' => $unit_weight,
                        'unit' => 'g',
                    ];
                } else if (isset($product_attrs['cj_payload']['productWeight'])) {
                    $weight = $product_attrs['cj_payload']['productWeight'];
                    $weight = explode('-', (string) $weight);
                    $unit_weight = $weight[count($weight) - 1] ?? 0;
                    $weight_breakdown[] = [
                        'item_id' => $item->id,
                        'source' => 'productWeight',
                        'weight' => $unit_weight,
                        'unit' => 'g',
                    ];
                }
            }

            $total_weight += (float) $unit_weight * $item->quantity;
        }
        Log::info('Cart weight summary', [
            'cart_id' => $this->id,
            'total_weight_g' => $total_weight,
            'weight_breakdown' => $weight_breakdown,
        ]);
        $total_weight_in_kg = $total_weight / 1000;

        $total_shipping = $default_warehouse->calculateShippingPerWeight($total_weight_in_kg);
        $defaultLine = [
            'cart_id' => $this['id'],
            'fulfillment_provider_id' => null,
            'logistic_name' => @$default_warehouse['shipping_company_name'],
            'logistic_price' => @$total_shipping,
            'total_postage_fee' => @$total_shipping,
            'aging' => null,
        ];
        $shippingLines[] = $defaultLine;
        if ($persist) {
            CartShipping::query()->create($defaultLine);
        }

        Log::info('Default warehouse shipping entry created', [
            'cart_id' => $this->id,
            'shipping_company' => @$default_warehouse['shipping_company_name'],
            'weight_kg' => $total_weight_in_kg,
            'shipping_charge' => $total_shipping,
            'warehouse_shipping_details' => [
                'min_charge' => $default_warehouse['shipping_min_charge'] ?? null,
                'base_cost' => $default_warehouse['shipping_base_cost'] ?? null,
                'cost_per_kg' => $default_warehouse['shipping_cost_per_kg'] ?? null,
                'additional_cost' => $default_warehouse['shipping_additional_cost'] ?? null,
            ],
        ]);

        return [
            'total' => (float) collect($shippingLines)->sum(fn ($line) => (float) ($line['logistic_price'] ?? 0)),
            'lines' => $shippingLines,
        ];
    }

    public function emptyCart()
    {
        $this->items()->delete();
        $this->shippings()->delete();
        $this->delete();
    }

    public static function GetCustomerOrGuestCart()
    {
        $customerId = auth('customer')->id();
        $sessionId = session()->id();

        return self::query()
            ->when(
                $customerId,
                function ($query) use ($customerId, $sessionId) {
                    $query->where(function ($scoped) use ($customerId, $sessionId) {
                        $scoped->where('user_id', $customerId)
                            ->orWhere(function ($guest) use ($sessionId) {
                                $guest->whereNull('user_id')
                                    ->where('session_id', $sessionId);
                            });
                    });
                },
                function ($query) use ($sessionId) {
                    $query->whereNull('user_id')
                        ->where('session_id', $sessionId);
                }
            )
            ->orderByDesc('updated_at')
            ->with('items')
            ->first();
    }

    public static function GetGuestCart()
    {
        return self::query()
            ->whereNull('user_id')
            ->where('session_id', session()->id())
            ->orderByDesc('updated_at')
            ->with('items')
            ->first();
    }

    public static function mergeCartAfterLogin($session_id)
    {
        $userId = auth('customer')->id();

        DB::transaction(function () use ($session_id, $userId) {

            // 1️⃣ Get session cart
            $sessionCart = Cart::with('items')
                ->where('session_id', $session_id)
                ->whereNull('user_id')
                ->first();

            if (!$sessionCart) {
                return;
            }

            // 2️⃣ Get user cart or create one
            $userCart = Cart::firstOrCreate(
                ['user_id' => $userId],
                ['session_id' => null]
            );

            // 3️⃣ Merge items
            foreach ($sessionCart->items as $item) {

                $existingItem = $userCart->items()
                    ->where('product_id', $item->product_id)
                    ->where('variant_id', $item->variant_id)
                    ->where('fulfillment_provider_id', $item->fulfillment_provider_id)
                    ->first();

                if ($existingItem) {
                    // Increase quantity
                    $existingItem->increment('qty', $item->qty);
                } else {
                    // Move item
                    $item->update([
                        'cart_id' => $userCart->id
                    ]);
                }
            }

            // 4️⃣ Delete session cart
            $sessionCart->items()->delete();
            $sessionCart->delete();
        });
    }

    public function getSummery()
    {
        $this->removeInvalidCjItems();

        $cart_items = $this->items;
        $customer = auth('customer')->user();

        $subtotal = $this->subTotal();
        $shipping = $this->calculateShippingFees();

        $coupon = session('cart_coupon');
        $discounts = calculateDiscounts($this, $cart_items, $coupon, $customer, $subtotal);
        $discount = @$discounts['amount']??0;
        $promotionDiscounts = @$discounts['promotion_discounts'] ?? [];


        $settings = SiteSetting::query()->first();

        $shippingTotal = applyShippingRules($shipping, $subtotal, $discount, $settings);
        $taxTotal = calculateTaxFromSettings(max(0, $subtotal - $discount), $settings);
        $taxIncluded = (bool)($settings?->tax_included ?? false);
        $total = $subtotal + $shippingTotal - $discount + ($taxIncluded ? 0 : $taxTotal);
        $cartContext = $this->buildCartContext($cart_items, $subtotal);

        $promotionEngine = app(PromotionEngine::class);
        $promotionModels = $promotionEngine->getApplicablePromotions($cartContext);
        $locale = app()->getLocale();
        $appliedPromotions = $promotionModels->map(function ($promo) use ($locale) {
            return [
                'id' => $promo->id,
                'name' => $promo->localizedValue('name', $locale) ?? $promo->name,
                'description' => $promo->localizedValue('description', $locale) ?? $promo->description,
                'type' => $promo->type,
                'value_type' => $promo->value_type,
                'value' => $promo->value,
                'start_at' => $promo->start_at,
                'end_at' => $promo->end_at,
                'targets' => $promo->targets,
                'conditions' => $promo->conditions,
            ];
        })->values()->all();

        $productIds = $cart_items->pluck('product_id')->filter()->unique()->values()->all();
        $categoryIds = $cart_items->map(fn($line) => $line->product?->category_id)->filter()->unique()->values()->all();
        $cartPromotions = app(PromotionHomepageService::class)->getPromotionsForPlacement('checkout', $productIds, $categoryIds);
        $minimumRequirement = app(CartMinimumService::class)->evaluate($subtotal, $discount, $shippingTotal, $promotionModels, $coupon);
        $selectedMethod = 'standard';

        $firstItem = $cart_items->first();
        $currency = $firstItem?->variant?->currency
            ?? $firstItem?->product?->currency
            ?? 'USD';

        return [
            'subtotal' => $subtotal,
            'shipping' => $shippingTotal,
            'shippingTotal' => $shippingTotal,
            'discount' => $discount,
            'coupon' => $coupon,
            'discount_label' => @$discounts['label'],
            'discount_source' => @$discounts['source'],
            'promotionDiscounts' => $promotionDiscounts,
            'appliedPromotions' => $appliedPromotions,
            'cartPromotions' => $cartPromotions,
            'minimum_cart_requirement' => $minimumRequirement,
            'tax_total' => $taxTotal,
            'tax_label' => $settings?->tax_label ?? 'Tax',
            'tax_included' => $taxIncluded,
            'total' => $total,
            'currency' => $currency,
            'shipping_method' => $selectedMethod,
        ];
    }

    protected function buildCartContext(Collection $cartItems, float $subtotal): array
    {
        return [
            'lines' => (CartResource::collection($cartItems))->jsonSerialize(),
            'subtotal' => $subtotal,
            'user_id' => auth('customer')->id(),
        ];
    }

    private function removeItemsByCjVid(string $cjVid): void
    {
        $this->items()
            ->whereHas('variant', function ($query) use ($cjVid) {
                $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.cj_vid')) = ?", [$cjVid]);
            })
            ->delete();
    }

    private function removeInvalidCjItems(): void
    {
        $invalid = $this->items()
            ->whereHas('product', function ($query) {
                $query->where('default_fulfillment_provider_id', 1);
            })
            ->where(function ($query) {
                $query->whereNull('variant_id')
                    ->orWhereDoesntHave('variant')
                    ->orWhereHas('variant', function ($variantQuery) {
                        $variantQuery->whereRaw("JSON_EXTRACT(metadata, '$.cj_vid') is null");
                    });
            })
            ->pluck('id');

        if ($invalid->isEmpty()) {
            return;
        }

        $this->items()->whereIn('id', $invalid)->delete();
        $this->unsetRelation('items');

        Log::warning('Removed invalid CJ cart items before pricing/shipping', [
            'cart_id' => $this->id,
            'item_ids' => $invalid->values()->all(),
        ]);
    }
}
