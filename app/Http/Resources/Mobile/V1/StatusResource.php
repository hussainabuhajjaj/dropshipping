<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Http\Resources\Mobile\V1\Concerns\WithoutSuccessWrapper;

class StatusResource extends \App\Http\Resources\Storefront\StatusResource
{
    use WithoutSuccessWrapper;
}
