<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class VisitorLocationResolver
{
    /**
     * @return array{country_code:?string,country_name:?string,region_name:?string,city_name:?string,latitude:?float,longitude:?float}
     */
    public function resolve(Request $request): array
    {
        $headerLocation = $this->resolveFromHeaders($request);
        $ip = (string) $request->ip();

        if ($this->hasMeaningfulLocation($headerLocation)) {
            return $headerLocation;
        }

        if ($ip === '' || $this->isPrivateOrLocalIp($ip)) {
            return $headerLocation;
        }

        return Cache::remember(
            'visitor-geo:' . sha1($ip),
            now()->addDay(),
            fn (): array => $this->lookupByIp($ip) ?: $headerLocation
        );
    }

    /**
     * @return array{country_code:?string,country_name:?string,region_name:?string,city_name:?string,latitude:?float,longitude:?float}
     */
    private function resolveFromHeaders(Request $request): array
    {
        $countryCode = $this->cleanText(
            $request->headers->get('CF-IPCountry')
            ?: $request->headers->get('X-Vercel-IP-Country')
            ?: $request->headers->get('CloudFront-Viewer-Country')
            ?: $request->headers->get('X-Appengine-Country')
        );

        $city = $this->cleanText(
            $request->headers->get('X-Vercel-IP-City')
            ?: $request->headers->get('CloudFront-Viewer-City')
            ?: $request->headers->get('X-Appengine-City')
        );

        $region = $this->cleanText(
            $request->headers->get('X-Vercel-IP-Country-Region')
            ?: $request->headers->get('X-Appengine-Region')
        );

        return [
            'country_code' => $countryCode,
            'country_name' => null,
            'region_name' => $region,
            'city_name' => $city,
            'latitude' => null,
            'longitude' => null,
        ];
    }

    /**
     * @return array{country_code:?string,country_name:?string,region_name:?string,city_name:?string,latitude:?float,longitude:?float}|null
     */
    private function lookupByIp(string $ip): ?array
    {
        try {
            $response = Http::timeout(1.5)
                ->acceptJson()
                ->get("https://ipapi.co/{$ip}/json/");

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();

            if (! is_array($data) || ($data['error'] ?? false)) {
                return null;
            }

            return [
                'country_code' => $this->cleanText($data['country_code'] ?? null),
                'country_name' => $this->cleanText($data['country_name'] ?? null),
                'region_name' => $this->cleanText($data['region'] ?? null),
                'city_name' => $this->cleanText($data['city'] ?? null),
                'latitude' => isset($data['latitude']) && is_numeric($data['latitude']) ? (float) $data['latitude'] : null,
                'longitude' => isset($data['longitude']) && is_numeric($data['longitude']) ? (float) $data['longitude'] : null,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array{country_code:?string,country_name:?string,region_name:?string,city_name:?string,latitude:?float,longitude:?float} $location
     */
    private function hasMeaningfulLocation(array $location): bool
    {
        return (bool) ($location['country_code'] || $location['country_name'] || $location['city_name']);
    }

    private function isPrivateOrLocalIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    private function cleanText(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }
}
