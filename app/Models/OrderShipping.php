<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderShipping extends Model
{
    protected $fillable = [
        'order_id', 'name', 'price', 'total_postage_fee',
        'aging', 'fulfillment_provider_id',
    ];
}
