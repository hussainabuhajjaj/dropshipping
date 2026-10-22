<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Storefront\Address;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|nullable|string|max:255',
            'phone' => 'sometimes|nullable|string|max:30',
            'line1' => 'sometimes|required|string|max:255',
            'line2' => 'sometimes|nullable|string|max:255',
            'city' => 'sometimes|nullable|string|max:255',
            'state' => 'sometimes|nullable|string|max:255',
            'postal_code' => 'sometimes|nullable|string|max:20',
            'country' => 'sometimes|nullable|string|max:2',
            'type' => 'sometimes|nullable|string|max:20',
            'is_default' => 'sometimes|boolean',
        ];
    }
}
