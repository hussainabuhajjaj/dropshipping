<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Mobile\V1\Payments;

use App\Http\Requests\Api\Mobile\V1\BaseRequest;

class KorapayVerifyRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'max:255'],
        ];
    }
}
