<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Mobile\V1\Cart;

use App\Http\Requests\Api\Mobile\V1\BaseRequest;

class UpdateItemRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
