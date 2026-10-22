<?php

declare(strict_types=1);

namespace App\Infrastructure\Fulfillment\Clients\CJ;

use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Services\Api\ApiClient;

abstract class CjBaseApi
{
    public function __construct(protected CJDropshippingClient $root)
    {
    }

    /**
     * Return a client that will automatically refresh the CJ access token and retry once on 401.
     * This keeps callers (product APIs) unchanged while improving resilience.
     */
    protected function client(): object
    {
        $apiClient = $this->root->authClient();
        $root = $this->root;

        return new class($apiClient, $root) {
            private ApiClient $client;
            private CJDropshippingClient $root;

            public function __construct(ApiClient $client, CJDropshippingClient $root)
            {
                $this->client = $client;
                $this->root = $root;
            }

            public function __call(string $name, array $arguments)
            {
                try {
                    return $this->client->{$name}(...$arguments);
                } catch (\Throwable $e) {
                    // Normalize status codes across ApiException and RequestException
                    $status = null;
                    if ($e instanceof \App\Services\Api\ApiException) {
                        $status = $e->status;
                    } elseif ($e instanceof \Illuminate\Http\Client\RequestException) {
                        $status = $e->response?->status();
                    }

                    // Attempt one refresh+retry on 401 Unauthorized responses
                    if ($status === 401) {
                        try {
                            $this->root->getAccessToken(true);
                            $this->client = $this->root->authClient();
                            return $this->client->{$name}(...$arguments);
                        } catch (\Throwable) {
                            // ignore and fall through to rethrow original exception
                        }
                    }

                    throw $e;
                }
            }
        };
    }
}
