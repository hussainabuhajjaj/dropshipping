<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Storefront\Payment;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => 'required|string|max:40',
            'brand' => 'nullable|string|max:40',
            'last4' => 'nullable|string|size:4',
            'exp_month' => 'nullable|integer|min:1|max:12',
            'exp_year' => 'nullable|integer|min:2024|max:2100',
            'nickname' => 'nullable|string|max:80',
            'is_default' => 'nullable|boolean',
        ];
    }
}
