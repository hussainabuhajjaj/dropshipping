<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LibreTranslateClient implements TranslationProvider
{
    private string $baseUrl;
    private ?string $apiKey;
    private int $timeout;

    public function __construct()
    {
        $config = config('services.libre_translate', []);
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? 'https://libretranslate.de'), '/');
        $this->apiKey = (string) ($config['key'] ?? '') ?: null;
        $this->timeout = (int) ($config['timeout'] ?? 10);
    }

    public function translate(string $text, string $source, string $target): string
    {
        if ($text === '') {
            return '';
        }

        try {
            $payload = [
                'q' => $text,
                'source' => $source,
                'target' => $target,
            ];

            if ($this->apiKey) {
                $payload['api_key'] = $this->apiKey;
            }

            logger()->info('LibreTranslate request', [
                'url' => "{$this->baseUrl}/translate",
                'payload' => $payload,
            ]);

            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/translate", $payload);

            $responseBody = $response->body();
            logger()->info('LibreTranslate raw response', [
                'status' => $response->status(),
                'body' => substr($responseBody, 0, 500),
            ]);

            $response->throw();

            $data = $response->json();
            
            if (!is_array($data)) {
                logger()->error('LibreTranslate response not array', [
                    'data' => $data,
                    'raw' => substr($responseBody, 0, 500),
                ]);
                throw new RuntimeException('LibreTranslate response invalid format.');
            }

            $translated = $data['translatedText'] ?? null;

            if (!is_string($translated) || $translated === '') {
                logger()->error('LibreTranslate response missing translatedText', [
                    'response' => $data,
                    'source' => $source,
                    'target' => $target,
                ]);
                throw new RuntimeException('LibreTranslate response missing translatedText.');
            }

            logger()->info('LibreTranslate result', [
                'source' => $source,
                'target' => $target,
                'input' => substr($text, 0, 100),
                'output' => substr($translated, 0, 100),
            ]);

            return trim($translated);
        } catch (RequestException $e) {
            logger()->error('LibreTranslate HTTP error', [
                'message' => $e->getMessage(),
                'status' => $e->response->status() ?? null,
                'body' => substr($e->response->body() ?? '', 0, 500),
            ]);
            throw new RuntimeException('LibreTranslate request failed: ' . $e->getMessage(), 0, $e);
        } catch (\Exception $e) {
            logger()->error('LibreTranslate exception', [
                'message' => $e->getMessage(),
                'class' => get_class($e),
            ]);
            throw $e;
        }
    }
}
