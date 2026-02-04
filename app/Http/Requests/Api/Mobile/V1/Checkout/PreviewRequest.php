<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Mobile\V1\Checkout;

use App\Http\Requests\Api\Mobile\V1\BaseRequest;

class PreviewRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'email' => ['nullable', 'email'],
            'country' => ['nullable', 'string', 'max:2'],
        ];
    }
}
