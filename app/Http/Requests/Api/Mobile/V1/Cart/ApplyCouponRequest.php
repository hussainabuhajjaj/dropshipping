<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Mobile\V1\Cart;

use App\Http\Requests\Api\Mobile\V1\BaseRequest;

class ApplyCouponRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255'],
        ];
    }
}
