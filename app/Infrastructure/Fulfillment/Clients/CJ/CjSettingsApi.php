<?php

declare(strict_types=1);

namespace App\Infrastructure\Fulfillment\Clients\CJ;

use App\Services\Api\ApiResponse;

class CjSettingsApi extends CjBaseApi
{
    public function getSettings(): ApiResponse
    {
        return $this->client()->get('/v1/setting/get');
    }

    public function updateAccount(?string $openName = null, ?string $openEmail = null): ApiResponse
    {
        $payload = array_filter([
            'openName' => $openName,
            'openEmail' => $openEmail,
        ], fn ($v) => $v !== null && $v !== '');

        return $this->client()->patch('/v1/setting/account/set', $payload);
    }
}
