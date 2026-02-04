<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Mobile\V1\Auth;

use App\Http\Requests\Api\Mobile\V1\BaseRequest;

class SendPhoneOtpRequest extends BaseRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $phone = preg_replace('/[^\d+]/', '', (string) $this->input('phone'));
            $this->merge(['phone' => $phone]);
        }
    }

    public function rules(): array
    {
        return [
            'phone' => [
                'nullable',
                'string',
                'max:40',
                'regex:/^(?:\\+?225)?(?:01|05|07|21|25|27)\\d{8}$/',
            ],
        ];
    }
}
