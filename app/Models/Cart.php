<?php

namespace App\Models;

//use App\Domain\Products\Models\ProductVariant;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use Illuminate\Database\Eloquent\Model;

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
        } , 0);
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

        CartShipping::query()->where('cart_id', $this->id)->delete();
        foreach ($providers as $provider_id => $items) {
            if ($provider_id == 1) {
                //  cj check shipping cost
                $client = app(CJDropshippingClient::class);
                $payload = [
                    'startCountryCode' => 'CN',
                    'endCountryCode' => 'CN',
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

                if (isset($result->data)) {
                    $data = collect($result->data);
                    $company = $data->sortBy('logisticPrice')->first();

                    if (isset($company)) {
                        CartShipping::query()->create([
                            'cart_id' => $this['id'],
                            'logistic_name' => @$company['logisticName'],
                            'logistic_price' => @$company['logisticPrice'],
                            'total_postage_fee' => @$company['totalPostageFee'],
                            'aging' => @$company['logisticAging'],
                        ]);
                    }


                }
            }
        }


        return CartShipping::query()->where('cart_id', $this->id)->sum('logistic_price');

    }
}
