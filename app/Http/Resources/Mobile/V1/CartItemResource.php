<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Http\Resources\Mobile\V1\Concerns\WithoutSuccessWrapper;

class CartItemResource extends \App\Http\Resources\User\CartResource
{
    use WithoutSuccessWrapper;
}
