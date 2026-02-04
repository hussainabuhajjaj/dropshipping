<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Http\Resources\Mobile\V1\Concerns\WithoutSuccessWrapper;

class PaymentMethodResource extends \App\Http\Resources\Storefront\PaymentMethodResource
{
    use WithoutSuccessWrapper;
}
