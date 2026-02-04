<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Mobile\V1;

class NewsletterSubscribeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'source' => ['nullable', 'string', 'max:80'],
        ];
    }
}
