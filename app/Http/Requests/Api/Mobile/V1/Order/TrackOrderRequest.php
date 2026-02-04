<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Mobile\V1\Order;

use App\Http\Requests\Api\Mobile\V1\BaseRequest;

class TrackOrderRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'number' => ['required', 'string', 'max:64'],
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
