<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Mobile\V1\Notifications;

use App\Http\Requests\Api\Mobile\V1\BaseRequest;

class NotificationIndexRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
