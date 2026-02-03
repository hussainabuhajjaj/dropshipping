<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Mobile\V1\Preferences;

use App\Http\Requests\Api\Mobile\V1\BaseRequest;

class PreferencesUpdateRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'country' => ['sometimes', 'string', 'max:80'],
            'currency' => ['sometimes', 'string', 'max:40'],
            'size' => ['sometimes', 'string', 'max:20'],
            'language' => ['sometimes', 'string', 'max:40'],
            'notifications' => ['sometimes', 'array'],
            'notifications.push' => ['sometimes', 'boolean'],
            'notifications.email' => ['sometimes', 'boolean'],
            'notifications.sms' => ['sometimes', 'boolean'],
        ];
    }
}
