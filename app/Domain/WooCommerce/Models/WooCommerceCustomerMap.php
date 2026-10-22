<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Models;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WooCommerceCustomerMap extends Model
{
    protected $table = 'woocommerce_customer_maps';

    protected $fillable = [
        'customer_id',
        'woocommerce_customer_id',
        'email',
        'status',
        'last_error',
        'last_synced_at',
    ];

    protected $casts = [
        'woocommerce_customer_id' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
