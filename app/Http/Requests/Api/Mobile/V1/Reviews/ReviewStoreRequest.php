<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Mobile\V1\Reviews;

use App\Http\Requests\Api\Mobile\V1\BaseRequest;

class ReviewStoreRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:2000'],
            'images' => ['sometimes', 'array'],
            'images.*' => ['file', 'image', 'max:4096'],
        ];
    }
}
