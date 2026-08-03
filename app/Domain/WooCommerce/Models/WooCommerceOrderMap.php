<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Models;

use App\Domain\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WooCommerceOrderMap extends Model
{
    protected $table = 'woocommerce_order_maps';

    protected $fillable = [
        'order_id',
        'woocommerce_order_id',
        'woocommerce_order_number',
        'status',
        'last_error',
        'last_synced_at',
    ];

    protected $casts = [
        'woocommerce_order_id' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
