<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartShipping extends Model
{
    protected $fillable = [
        'cart_id', 'logistic_name', 'logistic_price',
        'total_postage_fee', 'aging',
    ];
}
