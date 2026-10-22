<?php

declare(strict_types=1);

namespace App\Services\Meta;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaConversionsApiService
{
    public function enabled(): bool
    {
        return filled(config('services.meta_ads.dataset_id'))
            && filled(config('services.meta_ads.access_token'));
    }

    public function sendAppEvent(array $event): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $datasetId = (string) config('services.meta_ads.dataset_id');
        $accessToken = (string) config('services.meta_ads.access_token');
        $apiVersion = (string) config('services.meta_ads.api_version', 'v21.0');
        $testEventCode = config('services.meta_ads.test_event_code');

        $payload = [
            'data' => [$event],
        ];

        if (filled($testEventCode)) {
            $payload['test_event_code'] = $testEventCode;
        }

        $response = Http::timeout(10)
            ->acceptJson()
            ->withQueryParameters(['access_token' => $accessToken])
            ->post(
                sprintf(
                    'https://graph.facebook.com/%s/%s/events',
                    $apiVersion,
                    $datasetId,
                ),
                $payload,
            );

        if ($response->failed()) {
            Log::warning('Meta CAPI app event failed', [
                'status' => $response->status(),
                'body' => $response->json(),
                'event_name' => $event['event_name'] ?? null,
                'event_id' => $event['event_id'] ?? null,
            ]);

            return false;
        }

        return true;
    }

    public function normalizeAndHash(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return null;
        }

        return hash('sha256', $normalized);
    }

    public function normalizeAndHashPhone(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/\D+/', '', $value);
        if (! is_string($normalized) || $normalized === '') {
            return null;
        }

        return hash('sha256', $normalized);
    }
}
