<?php

declare(strict_types=1);

namespace App\Http\Resources\Storefront\Payment;

use App\Http\Resources\Storefront\JsonResource;
use Illuminate\Http\Request;

class CardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'brand' => $this['brand'],
            'nickname' => $this['nickname'],
            'number' => $this->getMaskedNumberAttribute(),
            'exp_date' => $this->getFormattedExpiryAttribute(),
        ];
    }
}
