<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Mobile\V1\Category;

use App\Http\Requests\Api\Mobile\V1\BaseRequest;

class CategoryShowRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'sort' => ['nullable', 'string', 'in:newest,price_asc,price_desc,rating,popular'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
