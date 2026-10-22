<?php

declare(strict_types=1);

namespace App\Infrastructure\Fulfillment\Clients\CJ;

use App\Services\Api\ApiResponse;
use Illuminate\Support\Facades\Cache;

class CjAuthApi extends CjBaseApi
{
    public function logout(): ApiResponse
    {
        $response = $this->client()->post('/v1/authentication/logout');
        Cache::forget('cj.access_token');
        Cache::forget('cj.refresh_token');
        return $response;
    }
}
