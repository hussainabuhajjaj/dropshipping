<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Order;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', 'in:pending,processing,shipped,delivered,cancelled'],
            'payment_status' => ['sometimes', 'string', 'in:unpaid,paid,refunded,failed'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
