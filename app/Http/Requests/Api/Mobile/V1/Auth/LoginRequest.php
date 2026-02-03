<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Mobile\V1\Auth;

use App\Http\Requests\Api\Mobile\V1\BaseRequest;

class LoginRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
