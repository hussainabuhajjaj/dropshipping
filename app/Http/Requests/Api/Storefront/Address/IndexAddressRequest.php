<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Storefront\Address;

use Illuminate\Foundation\Http\FormRequest;

class IndexAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
