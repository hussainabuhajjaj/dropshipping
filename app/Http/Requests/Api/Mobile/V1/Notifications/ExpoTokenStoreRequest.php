<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Mobile\V1\Notifications;

use App\Http\Requests\Api\Mobile\V1\BaseRequest;

class ExpoTokenStoreRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
        ];
    }
}

