<?php

use App\Models\SiteSetting;

function calculateTax(float $amount, $tax_rate = 0): float
{
    $rate = (float)$tax_rate;

    if ($rate <= 0) {
        return 0.0;
    }

    return round($amount * ($rate / 100), 2);
}
