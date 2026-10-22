<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Http\Resources\Mobile\V1\Concerns\WithoutSuccessWrapper;

use Illuminate\Http\Request;

class CustomerResource extends \App\Http\Resources\Storefront\CustomerResource
{
    use WithoutSuccessWrapper;
    public function toArray(Request $request): array
    {
        $payload = parent::toArray($request);

        $payload['email_verified_at'] = $this->email_verified_at?->toIso8601String();
        $payload['is_verified'] = (bool) $this->email_verified_at;
        $payload['phone_verified_at'] = $this->phone_verified_at?->toIso8601String();
        $payload['is_phone_verified'] = (bool) $this->phone_verified_at;
        return $payload;
    }
}
