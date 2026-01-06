<?php

namespace App\Domain\Orders\Actions;

use Illuminate\Support\Facades\Validator;

class EnforceMinimumOrder
{
    public static function check(float $totalUsd, float $totalXof): void
    {
        if ($totalUsd < 30 && $totalXof < 20000) {
            abort(403, 'Minimum order is $30 or 20,000 XOF.');
        }
    }
}
