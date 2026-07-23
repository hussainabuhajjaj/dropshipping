<?php

namespace App\Models;

use App\Http\Resources\User\CartResource;
use App\Infrastructure\Fulfillment\Clients\AliExpressClient;
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
        'user_id', 'session_id', 'visitor_id', 'product_id', 'fulfillment_provider_id',
        'variant_id', 'quantity', 'stock_on_hand', 'applied_coupon_code', 'applied_coupon_data'
    ];

    protected $casts = [
        'applied_coupon_data' => 'array',
    ];

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    public function shippings()
    {
        return $this->hasMany(CartShipping::class);
    }

    public static function createCart(array $attributes = []): self
    {
        $defaults = auth('customer')->check()
            ? ['user_id' => auth('customer')->id()]
            : [
                'session_id' => session()->id(),
                'visitor_id' => request()->cookie(\App\Services\Analytics\VisitTrackingService::WEBSITE_COOKIE),
            ];

        return self::query()->create(array_merge($defaults, $attributes));
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
     * @return array{total: float, lines: array<int, array<string, mixed>>, unavailable: bool, reason: ?string}
     */
    public function quoteShippingForItems(Collection $items, bool $persist = false): array
    {
        $this->removeInvalidCjItems();

        $providers = $items->groupBy('fulfillment_provider_id');
        $shippingLines = [];
        $shippingUnavailable = false;
        $shippingUnavailableReason = null;
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
            $firstProviderItem = $providerItems->first();
            $providerSupplierType = (string) ($firstProviderItem?->product?->supplier_type ?? '');

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
                    $shippingUnavailable = true;
                    $shippingUnavailableReason = 'No valid CJ variants found for shipping quote.';
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

                    if (! isset($company)) {
                        $shippingUnavailable = true;
                        $shippingUnavailableReason = 'CJ returned no shipping options for one or more cart items.';
                    }
                } catch (ApiException $e) {
                    $shippingUnavailable = true;
                    $shippingUnavailableReason = 'CJ shipping quote failed.';
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
                    $shippingUnavailable = true;
                    $shippingUnavailableReason = 'CJ shipping quote failed.';
                    Log::warning('Unexpected freight calculation failure; skipping provider shipping quote', [
                        'cart_id' => $this->id,
                        'provider_id' => $provider_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            } elseif ($providerSupplierType === 'aliexpress') {
                $client = app(AliExpressClient::class);
                $providerTotal = 0.0;
                $providerMaxDays = null;
                $providerMethods = [];

                foreach ($providerItems as $item) {
                    $variant = $item->variant ?? ProductVariant::query()->with('product.localWarehouse')->find($item['variant_id']);
                    $product = $item->product ?? $variant?->product;
                    $supplierProduct = \App\Domain\Products\Models\SupplierProduct::query()
                        ->where('product_variant_id', $item['variant_id'])
                        ->when($provider_id, fn ($query) => $query->where('fulfillment_provider_id', $provider_id))
                        ->first();

                    $warehouse = $product?->localWarehouse ?? $default_warehouse;
                    $shipToCountry = strtoupper((string) ($warehouse?->country ?? 'CN'));
                    $variantMetadata = is_array($variant?->metadata ?? null) ? $variant->metadata : [];
                    $productAttributes = is_array($product?->attributes ?? null) ? $product->attributes : [];
                    $productId = $supplierProduct?->external_product_id ?? ($productAttributes['ali_item_id'] ?? null);
                    $selectedSkuId = $supplierProduct?->external_sku ?? ($variantMetadata['ali_sku_id'] ?? null);

                    if (! $productId || ! $selectedSkuId) {
                        $shippingUnavailable = true;
                        $shippingUnavailableReason = 'AliExpress shipping quote failed because product mapping is incomplete.';
                        Log::warning('Skipping AliExpress freight quote because product mapping is incomplete', [
                            'cart_id' => $this->id,
                            'provider_id' => $provider_id,
                            'item_id' => $item->id,
                            'product_id' => $item->product_id,
                            'variant_id' => $item->variant_id,
                        ]);
                        continue 2;
                    }

                    try {
                        $result = $client->freightQuery([
                            'quantity' => (int) ($item['quantity'] ?? 1),
                            'shipToCountry' => $shipToCountry !== '' ? $shipToCountry : 'CN',
                            'productId' => (string) $productId,
                            'language' => 'en',
                            'locale' => 'en_US',
                            'selectedSkuId' => (string) $selectedSkuId,
                            'currency' => (string) ($variant?->currency ?? $product?->currency ?? 'USD'),
                        ]);

                        $options = collect(data_get($result, 'result.delivery_options', []))
                            ->filter(fn ($option) => is_array($option))
                            ->map(function (array $option) {
                                $feeCent = $option['shipping_fee_cent'] ?? null;
                                $fee = is_numeric($feeCent)
                                    ? ((float) $feeCent / 100)
                                    : ((isset($option['free_shipping']) && $option['free_shipping']) ? 0.0 : null);

                                return [
                                    'name' => $option['company'] ?? $option['code'] ?? 'AliExpress',
                                    'code' => $option['code'] ?? null,
                                    'price' => $fee,
                                    'max_days' => isset($option['max_delivery_days']) && is_numeric($option['max_delivery_days'])
                                        ? (int) $option['max_delivery_days']
                                        : null,
                                    'raw' => $option,
                                ];
                            })
                            ->filter(fn ($option) => $option['price'] !== null)
                            ->sortBy('price')
                            ->values();

                        $best = $options->first();
                        if (! $best) {
                            $shippingUnavailable = true;
                            $shippingUnavailableReason = 'AliExpress returned no delivery options for one or more cart items.';
                            continue 2;
                        }

                        $providerTotal += (float) $best['price'];
                        $providerMethods[] = $best['name'];
                        $providerMaxDays = $providerMaxDays === null
                            ? $best['max_days']
                            : max((int) $providerMaxDays, (int) ($best['max_days'] ?? 0));
                    } catch (\Throwable $e) {
                        $shippingUnavailable = true;
                        $shippingUnavailableReason = 'AliExpress shipping quote failed.';
                        Log::warning('AliExpress freight calculation failed; skipping provider shipping quote', [
                            'cart_id' => $this->id,
                            'provider_id' => $provider_id,
                            'item_id' => $item->id,
                            'error' => $e->getMessage(),
                        ]);
                        continue 2;
                    }
                }

                $line = [
                    'cart_id' => $this['id'],
                    'fulfillment_provider_id' => $provider_id,
                    'logistic_name' => implode(' + ', array_values(array_unique(array_filter($providerMethods)))),
                    'logistic_price' => round($providerTotal, 2),
                    'total_postage_fee' => round($providerTotal, 2),
                    'aging' => $providerMaxDays,
                ];
                $shippingLines[] = $line;
                if ($persist) {
                    CartShipping::query()->create($line);
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

        return [
            'total' => (float) collect($shippingLines)->sum(fn ($line) => (float) ($line['logistic_price'] ?? 0)),
            'lines' => $shippingLines,
            'unavailable' => $shippingUnavailable,
            'reason' => $shippingUnavailableReason,
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
        return app(\App\Services\Cart\CartIdentityService::class)->resolveCart(request(), auth('customer')->user());
    }

    public static function GetGuestCart()
    {
        return app(\App\Services\Cart\CartIdentityService::class)->resolveCart(request(), null);
    }

    public static function mergeCartAfterLogin($session_id)
    {
        $customer = auth('customer')->user();
        if (! $customer) {
            return null;
        }

        return app(\App\Services\Cart\CartIdentityService::class)
            ->mergeGuestCartIntoCustomer(request(), $customer, $session_id);
    }

    public function getSummery()
    {
        $this->removeInvalidCjItems();

        $cart_items = $this->items;
        $customer = auth('customer')->user();

        $subtotal = $this->subTotal();
        $shipping = $this->calculateShippingFees();

        $coupon = $this->applied_coupon_data ?? session('cart_coupon');
        $discounts = calculateDiscounts($this, $cart_items, $coupon, $customer, $subtotal);
        $discount = @$discounts['amount']??0;
        $promotionDiscounts = @$discounts['promotion_discounts'] ?? [];


        $settings = SiteSetting::query()->first();

        $shippingTotal = applyShippingRules($shipping, $subtotal, $discount, $settings);
        $taxTotal = calculateTaxFromSettings(max(0, $subtotal - $discount), $settings);
        $taxIncluded = (bool)($settings?->tax_included ?? false);
        $total = $subtotal + $shippingTotal - $discount + ($taxIncluded ? 0 : $taxTotal);
        $firstItem = $cart_items->first();
        $currency = $firstItem?->variant?->currency
            ?? $firstItem?->product?->currency
            ?? 'USD';
        
        // Debug: Log cart calculation details
        Log::info('Cart::getSummery calculation', [
            'subtotal' => $subtotal,
            'shipping_total' => $shippingTotal,
            'discount' => $discount,
            'tax_total' => $taxTotal,
            'tax_included' => $taxIncluded,
            'final_total' => $total,
            'currency' => $currency,
            'cart_items_count' => $cart_items->count(),
        ]);
        
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
            // Add raw structure for frontend PaymentSummary compatibility
            'raw' => [
                'subtotal' => $subtotal,
                'shipping' => $shippingTotal,
                'discount' => $discount,
                'tax_total' => $taxTotal,
                'total' => $total,
                'currency' => $currency,
            ],
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
