<?php

namespace App\Services\Providers\AliExpress;

use App\Services\Providers\ProductProviderInterface;
use App\Services\Providers\OrderProviderInterface;
use GuzzleHttp\Client;

class AliExpressProvider implements ProductProviderInterface, OrderProviderInterface
{
    protected Client $client;
    protected string $clientId;
    protected string $clientSecret;
    protected string $redirectUri;
    protected string $apiBase;

    public function __construct()
    {
        $this->client = new Client();
        $this->clientId = config('ali_express.client_id');
        $this->clientSecret = config('ali_express.client_secret');
        $this->redirectUri = config('ali_express.redirect_uri');
        $this->apiBase = config('ali_express.api_base');
    }

    public function searchProducts(array $params): array
    {
        // TODO: Implement AliExpress product search API call
        return [];
    }

    public function getProductDetails(string $externalId): array
    {
        // TODO: Implement AliExpress product details API call
        return [];
    }

    public function createOrder(array $orderData): array
    {
        // TODO: Implement AliExpress order creation API call
        return [];
    }

    public function getOrder(string $externalId): array
    {
        // TODO: Implement AliExpress get order API call
        return [];
    }

    public function trackOrder(string $externalId): array
    {
        // TODO: Implement AliExpress order tracking API call
        return [];
    }
}
