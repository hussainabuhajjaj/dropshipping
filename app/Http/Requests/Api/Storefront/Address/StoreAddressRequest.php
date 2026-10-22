<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Storefront\Address;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
            'line1' => 'required|string|max:255',
            'line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:2',
            'type' => 'nullable|string|max:20',
            'is_default' => 'nullable|boolean',
        ];
    }
}
