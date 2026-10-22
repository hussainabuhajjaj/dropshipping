<?php

namespace App\Services\Providers;

use App\Services\Providers\AliExpress\AliExpressProvider;
use App\Services\Providers\CJ\CJProvider;

class ProviderFactory
{
    public static function make(string $provider): ProductProviderInterface & OrderProviderInterface
    {
        return match (strtolower($provider)) {
            'aliexpress' => new AliExpressProvider(),
            'cj' => new CJProvider(),
            default => throw new \InvalidArgumentException('Unknown provider: ' . $provider),
        };
    }
}
