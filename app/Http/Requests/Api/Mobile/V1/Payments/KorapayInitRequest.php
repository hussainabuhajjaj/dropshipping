<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Mobile\V1\Payments;

use App\Http\Requests\Api\Mobile\V1\BaseRequest;

class KorapayInitRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'method' => ['required', 'in:card,mobile_money'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:3'],
            'customer' => ['nullable', 'array'],
            'customer.email' => ['nullable', 'email'],
            'customer.name' => ['nullable', 'string', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:20'],
            // Mobile deep links like "simbazu://payment-return" are valid app callbacks but fail strict URL validation.
            'return_url' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
