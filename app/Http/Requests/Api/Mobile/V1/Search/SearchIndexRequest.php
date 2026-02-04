<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Mobile\V1\Search;

use App\Http\Requests\Api\Mobile\V1\BaseRequest;

class SearchIndexRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'sort' => ['nullable', 'string', 'in:newest,price_asc,price_desc,rating,popular'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'categories_limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }
}

