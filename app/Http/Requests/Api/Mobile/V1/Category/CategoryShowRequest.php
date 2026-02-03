<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Mobile\V1\Category;

use App\Http\Requests\Api\Mobile\V1\BaseRequest;

class CategoryShowRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
