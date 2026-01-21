<?php

namespace App\Domain\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class LinehaulShipment extends Model
{
    protected $fillable = [
        'order_id',
        'tracking_number',
        'total_weight_kg',
        'base_fee',
        'per_kg_rate',
        'total_fee',
        'shipment_snapshot',
        'dispatched_at',
        'arrived_at',
        'cj_order_id',
        'cj_order_num',
        'cj_order_status',
        'cj_order_amount',
        'cj_product_amount',
        'cj_postage_amount',
        'cj_order_weight',
        'cj_logistic_name',
        'cj_tracking_url',
        'cj_shipping_country_code',
        'cj_shipping_province',
        'cj_shipping_city',
        'cj_shipping_phone',
        'cj_shipping_address',
        'cj_shipping_customer_name',
        'cj_remark',
        'cj_storage_id',
        'cj_storage_name',
        'cj_created_at',
        'cj_paid_at',
        'cj_store_created_at',
    ];

    protected $casts = [
        'shipment_snapshot' => 'array',
        'dispatched_at' => 'datetime',
        'arrived_at' => 'datetime',
        'cj_created_at' => 'datetime',
        'cj_paid_at' => 'datetime',
        'cj_store_created_at' => 'datetime',
        'cj_order_amount' => 'decimal:2',
        'cj_product_amount' => 'decimal:2',
        'cj_postage_amount' => 'decimal:2',
        'cj_order_weight' => 'decimal:3',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Apply a CJ order list payload to dedicated columns + snapshot.
     *
     * @param array<string, mixed> $cjOrder
     */
    public function applyCjOrder(array $cjOrder): void
    {
        $snapshot = $this->shipment_snapshot ?? [];
        $snapshot['cj_order'] = $cjOrder;
        $this->shipment_snapshot = $snapshot;

        $this->cj_order_id = $cjOrder['orderId'] ?? $this->cj_order_id;
        $this->cj_order_num = $cjOrder['orderNum'] ?? $this->cj_order_num;
        $this->cj_order_status = $cjOrder['orderStatus'] ?? $this->cj_order_status;
        $this->cj_order_amount = $cjOrder['orderAmount'] ?? $this->cj_order_amount;
        $this->cj_product_amount = $cjOrder['productAmount'] ?? $this->cj_product_amount;
        $this->cj_postage_amount = $cjOrder['postageAmount'] ?? $this->cj_postage_amount;
        $this->cj_order_weight = $cjOrder['orderWeight'] ?? $this->cj_order_weight;
        $this->cj_logistic_name = $cjOrder['logisticName'] ?? $this->cj_logistic_name;
        $this->cj_tracking_url = $cjOrder['trackingUrl'] ?? $this->cj_tracking_url;
        $this->cj_shipping_country_code = $cjOrder['shippingCountryCode'] ?? $this->cj_shipping_country_code;
        $this->cj_shipping_province = $cjOrder['shippingProvince'] ?? $this->cj_shipping_province;
        $this->cj_shipping_city = $cjOrder['shippingCity'] ?? $this->cj_shipping_city;
        $this->cj_shipping_phone = $cjOrder['shippingPhone'] ?? $this->cj_shipping_phone;
        $this->cj_shipping_address = $cjOrder['shippingAddress'] ?? $this->cj_shipping_address;
        $this->cj_shipping_customer_name = $cjOrder['shippingCustomerName'] ?? $this->cj_shipping_customer_name;
        $this->cj_remark = $cjOrder['remark'] ?? $this->cj_remark;
        $this->cj_storage_id = $cjOrder['storageId'] ?? $this->cj_storage_id;
        $this->cj_storage_name = $cjOrder['storageName'] ?? $this->cj_storage_name;

        $this->cj_created_at = $this->parseCjDate($cjOrder['createDate'] ?? null) ?? $this->cj_created_at;
        $this->cj_paid_at = $this->parseCjDate($cjOrder['paymentDate'] ?? null) ?? $this->cj_paid_at;
        $this->cj_store_created_at = $this->parseCjDate($cjOrder['storeCreateDate'] ?? null) ?? $this->cj_store_created_at;

        if (! empty($cjOrder['trackNumber'])) {
            $this->tracking_number = $cjOrder['trackNumber'];
        }

        $status = $cjOrder['orderStatus'] ?? null;
        if ($status === 'SHIPPED' && ! $this->dispatched_at) {
            $this->dispatched_at = now();
        }
        if ($status === 'DELIVERED' && ! $this->arrived_at) {
            $this->arrived_at = now();
        }
    }

    private function parseCjDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
