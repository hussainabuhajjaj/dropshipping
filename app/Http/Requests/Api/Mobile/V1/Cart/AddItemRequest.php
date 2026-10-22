<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Mobile\V1\Cart;

use App\Http\Requests\Api\Mobile\V1\BaseRequest;

class AddItemRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
