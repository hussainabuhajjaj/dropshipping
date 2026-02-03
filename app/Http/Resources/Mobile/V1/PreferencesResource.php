<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Http\Resources\Mobile\V1\Concerns\WithoutSuccessWrapper;
use Illuminate\Http\Request;

class PreferencesResource extends \App\Http\Resources\Storefront\JsonResource
{
    use WithoutSuccessWrapper;

    public function toArray(Request $request): array
    {
        $data = is_array($this->resource) ? $this->resource : [];

        return [
            'country' => $data['country'] ?? null,
            'currency' => $data['currency'] ?? null,
            'size' => $data['size'] ?? null,
            'language' => $data['language'] ?? null,
            'notifications' => [
                'push' => (bool) ($data['notifications']['push'] ?? false),
                'email' => (bool) ($data['notifications']['email'] ?? false),
                'sms' => (bool) ($data['notifications']['sms'] ?? false),
            ],
        ];
    }
}
