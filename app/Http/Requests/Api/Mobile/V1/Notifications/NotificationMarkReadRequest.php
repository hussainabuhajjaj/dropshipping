<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Mobile\V1\Notifications;

use App\Http\Requests\Api\Mobile\V1\BaseRequest;

class NotificationMarkReadRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'id' => ['nullable', 'uuid', 'required_without:ids'],
            'ids' => ['nullable', 'array', 'min:1', 'required_without:id'],
            'ids.*' => ['uuid'],
        ];
    }
}
