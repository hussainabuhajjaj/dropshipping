<?php

namespace App\Models;

//use App\Domain\Products\Models\ProductVariant;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use Illuminate\Database\Eloquent\Model;
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
        if (auth('web')->check()) {
            return self::query()->create(['user_id' => auth('web')->id()]);
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

        $items = $this->items;
        $providers = $items->groupBy('fulfillment_provider_id');
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

        CartShipping::query()->where('cart_id', $this->id)->delete();
        $default_warehouse = LocalWareHouse::query()->where('is_default', 1)->first();

        foreach ($providers as $provider_id => $items) {
            Log::info('Evaluating provider shipping group', [
                'cart_id' => $this->id,
                'provider_id' => $provider_id,
                'line_ids' => $items->pluck('id')->values()->all(),
            ]);
            if ($provider_id == 1) {
                //  cj check shipping cost
                $client = app(CJDropshippingClient::class);

                $payload = [
                    'startCountryCode' => 'CN',
                    'endCountryCode' => @$default_warehouse->country ?? "CN",
                    "products" => $items->map(function ($item) {
                        $vid = null;
                        if (isset($item['variant_id'])) {
                            $variant = ProductVariant::query()->find($item['variant_id']);
                            $vid = $variant->cj_vid;
                        } else {
                            $product = Product::query()->find($item['product_id']);
                            $vid = $product->cj_pid;
                        }
                        return [
                            "quantity" => @$item['quantity'] ?? 1,
                            "vid" => $vid
                        ];
                    })->toArray(),
                ];
                $result = $client->freightCalculate($payload);
//dd($result);
                if (isset($result->data)) {
                    $data = collect($result->data);
                    $company = $data->sortBy('logisticPrice')->first();

                    if (isset($company)) {
                        CartShipping::query()->create([
                            'cart_id' => $this['id'],
                            'fulfillment_provider_id' => $provider_id,
                            'logistic_name' => @$company['logisticName'],
                            'logistic_price' => @$company['logisticPrice'],
                            'total_postage_fee' => @$company['totalPostageFee'],
                            'aging' => @$company['logisticAging'],
                        ]);
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
//dd($weight_breakdown,$total_weight,$product_attrs['cj_payload']['packingWeight']);
            $total_weight += (float) $unit_weight * $item->quantity;

        }
        Log::info('Cart weight summary', [
            'cart_id' => $this->id,
            'total_weight_g' => $total_weight ,
            'weight_breakdown' => $weight_breakdown,
        ]);
        $total_weight_in_kg = $total_weight / 1000;

        $total_shipping = $default_warehouse->calculateShippingPerWeight($total_weight_in_kg);
        CartShipping::query()->create([
            'cart_id' => $this['id'],
            'fulfillment_provider_id' => null,
            'logistic_name' => @$default_warehouse['shipping_company_name'],
            'logistic_price' => @$total_shipping,
            'total_postage_fee' => @$total_shipping,
            'aging' => null,
        ]);

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

        return CartShipping::query()->where('cart_id', $this->id)->sum('logistic_price');

    }

    public function emptyCart()
    {
        $this->items()->delete();
        $this->shippings()->delete();
        $this->delete();
    }

    public static function GetCustomerOrGuestCart()
    {
        return self::query()->where('user_id', auth('customer')->id())
            ->orWhere('session_id', session()->id())
            ->with('items')
            ->first();
    }

    public static function GetGuestCart()
    {
        return self::query()
            ->orWhere('session_id', session()->id())
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
}
