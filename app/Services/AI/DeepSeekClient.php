<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DeepSeekClient implements TranslationProvider
{
    private string $baseUrl;
    private string $model;
    private string $apiKey;
    private int $connectTimeout;
    private int $timeout;
    private int $retryTimes;
    private int $retryDelayMs;
    private int $rateLimit = 60;
    private int $ratePeriod = 60;

    public function __construct()
    {
        $config = config('services.deepseek', []);
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? 'https://api.deepseek.com'), '/');
        $this->model = (string) ($config['model'] ?? 'deepseek-chat');
        $this->apiKey = (string) ($config['key'] ?? '');
        $this->connectTimeout = (int) ($config['connect_timeout'] ?? 10);
        $this->timeout = (int) ($config['timeout'] ?? 45);
        $this->retryTimes = max(0, (int) ($config['retry_times'] ?? 3));
        $this->retryDelayMs = max(0, (int) ($config['retry_delay_ms'] ?? 500));
        // optional rate limit settings
        $this->rateLimit = (int) ($config['rate_limit'] ?? $this->rateLimit);
        $this->ratePeriod = (int) ($config['rate_limit_period'] ?? $this->ratePeriod);
    }

    public function translate(string $text, string $source, string $target): string
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('DeepSeek API key is not configured.');
        }

        if ($text === '') {
            return '';
        }

        $targetName = $this->localeToLanguage($target);

        $result = $this->chat([
            [
                'role' => 'system',
                'content' => 'You are a translator. Output ONLY the translation in ' . $targetName . '. No explanations or anything else.',
            ],
            [
                'role' => 'user',
                'content' => "Translate to {$targetName}: {$text}",
            ],
        ], 0.1);

        logger()->info('Translation result', [
            'source' => $source,
            'target' => $target,
            'input' => substr($text, 0, 100),
            'output' => substr($result, 0, 100),
        ]);

        return $result;
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     */
    public function chat(array $messages, float $temperature = 0.4): string
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('DeepSeek API key is not configured.');
        }

        // Simple rate limiting using Laravel RateLimiter
        $key = 'deepseek:chat';
        if (class_exists(\Illuminate\Support\Facades\RateLimiter::class)) {
            if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, $this->rateLimit)) {
                throw new RuntimeException('DeepSeek rate limit exceeded.');
            }
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->connectTimeout($this->connectTimeout)
                ->timeout($this->timeout)
                ->retry($this->retryTimes, $this->retryDelayMs, function ($exception): bool {
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }

                    if ($exception instanceof RequestException) {
                        $status = $exception->response?->status();
                        return in_array($status, [408, 425, 429, 500, 502, 503, 504], true);
                    }

                    return false;
                })
                ->post("{$this->baseUrl}/v1/chat/completions", [
                    'model' => $this->model,
                    'temperature' => $temperature,
                    'messages' => $messages,
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('DeepSeek request failed: ' . $exception->getMessage(), 0, $exception);
        }

        try {
            $response->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException('DeepSeek request failed: ' . $exception->getMessage(), 0, $exception);
        }

        // Mark attempt after successful response
        if (class_exists(\Illuminate\Support\Facades\RateLimiter::class)) {
            \Illuminate\Support\Facades\RateLimiter::hit($key, $this->ratePeriod);
        }

        $data = $response->json();
        $content = $data['choices'][0]['message']['content'] ?? null;

        if (! is_string($content)) {
            throw new RuntimeException('DeepSeek response missing content.');
        }

        return trim($content);
    }

    public function localeToLanguage(string $locale): string
    {
        $map = [
            'en' => 'English',
            'fr' => 'French',
            'es' => 'Spanish',
            'de' => 'German',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'nl' => 'Dutch',
            'pl' => 'Polish',
            'sv' => 'Swedish',
            'fi' => 'Finnish',
            'no' => 'Norwegian',
            'da' => 'Danish',
            'ru' => 'Russian',
            'zh' => 'Chinese',
            'zh-cn' => 'Chinese (Simplified)',
            'zh-tw' => 'Chinese (Traditional)',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'ar' => 'Arabic',
        ];

        $key = strtolower(trim($locale));
        return $map[$key] ?? $locale;
    }
}
