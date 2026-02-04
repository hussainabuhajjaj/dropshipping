<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Mobile\V1\Auth;

use App\Http\Requests\Api\Mobile\V1\BaseRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends BaseRequest
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
        $customerId = $this->user()?->id ?? null;

        return [
            'name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($customerId)],
            'phone' => [
                'nullable',
                'string',
                'max:40',
                'regex:/^(?:\\+?225)?(?:01|05|07|21|25|27)\\d{8}$/',
            ],
            'avatar' => ['nullable', 'string'],
        ];
    }
}
