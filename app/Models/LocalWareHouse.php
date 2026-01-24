<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocalWareHouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'line1',
        'line2',
        'city',
        'state',
        'postal_code',
        'country',
        'is_default',
        'shipping_company_name', 'shipping_method', 'shipping_min_charge', 'shipping_cost_per_kg', 'shipping_additional_cost'
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function calculateShippingPerWeight($weight)
    {
        $shipping_for_weight = $weight * $this->shipping_cost_per_kg;
        $total = $shipping_for_weight + $this->shipping_base_cost + $this->shipping_additional_cost;
        return $total > $this->shipping_min_charge ? $total : $this->shipping_min_charge;
    }
}
