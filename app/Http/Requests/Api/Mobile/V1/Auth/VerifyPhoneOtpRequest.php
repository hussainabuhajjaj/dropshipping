<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Mobile\V1\Auth;

use App\Http\Requests\Api\Mobile\V1\BaseRequest;

class VerifyPhoneOtpRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:4'],
        ];
    }
}
