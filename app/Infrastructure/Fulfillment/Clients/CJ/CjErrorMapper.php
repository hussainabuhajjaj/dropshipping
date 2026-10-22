<?php

declare(strict_types=1);

namespace App\Infrastructure\Fulfillment\Clients\CJ;

class CjErrorMapper
{
    /**
     * Return a human-friendly remediation hint for a given CJ error.
     */
    public static function hint(?string $codeString, ?int $status, mixed $body): ?string
    {
        $code = null;
        if ($codeString !== null) {
            $code = is_numeric($codeString) ? (int) $codeString : null;
        }
        if (! $code && is_array($body) && array_key_exists('code', $body)) {
            $code = is_numeric($body['code']) ? (int) $body['code'] : null;
        }

        return match ($code) {
            1600101 => 'Interface not found: confirm the endpoint path and HTTP method (see CJ docs). Check services.cj.base_url and the `/v1/product/myProduct/query` endpoint.',
            1600001, 1600002, 1600003 => 'Authentication problem: validate CJ API credentials and token caching (cj.access_token). Re-obtain access token per CJ docs.',
            1600200, 1600201 => 'Rate limit exceeded: slow down requests or move to scheduled sync with backoff.',
            1600300 => 'Parameter error: verify request parameters and JSON format (check query vs JSON body).',
            1600500 => 'System error: CJ internal error. Retry later and contact CJ support if persistent.',
            default => null,
        };
    }
}
