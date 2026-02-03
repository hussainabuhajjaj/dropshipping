<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Mobile\V1\Auth;

use App\Http\Requests\Api\Mobile\V1\BaseRequest;

class RegisterRequest extends BaseRequest
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
            'name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:customers,email'],
            'phone' => [
                'required',
                'string',
                'max:40',
                'regex:/^(?:\\+?225)?(?:01|05|07|21|25|27)\\d{8}$/',
            ],
            'password' => ['required', 'string', 'min:6'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'avatar' => ['nullable', 'string'],
        ];
    }
}
