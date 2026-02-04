<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Storefront\Order;

use Illuminate\Foundation\Http\FormRequest;

class TrackOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'number' => ['required', 'string', 'max:64'],
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
