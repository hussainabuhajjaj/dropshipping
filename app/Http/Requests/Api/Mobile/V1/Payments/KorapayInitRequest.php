<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Mobile\V1\Payments;

use App\Http\Requests\Api\Mobile\V1\BaseRequest;

class KorapayInitRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'order_number' => ['required', 'string', 'max:64'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:3'],
            'customer' => ['nullable', 'array'],
            'customer.email' => ['nullable', 'email'],
            'customer.name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
