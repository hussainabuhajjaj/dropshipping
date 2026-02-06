<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DeepSeekClient implements TranslationProvider
{
    private string $baseUrl;
    private string $model;
    private string $apiKey;
    private int $timeout;
    private int $rateLimit = 60;
    private int $ratePeriod = 60;

    public function __construct()
    {
        $config = config('services.deepseek', []);
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? 'https://api.deepseek.com'), '/');
        $this->model = (string) ($config['model'] ?? 'deepseek-chat');
        $this->apiKey = (string) ($config['key'] ?? '');
        $this->timeout = (int) ($config['timeout'] ?? 20);
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

        $response = Http::withToken($this->apiKey)
            ->timeout($this->timeout)
            ->retry(2, 100)
            ->post("{$this->baseUrl}/v1/chat/completions", [
                'model' => $this->model,
                'temperature' => $temperature,
                'messages' => $messages,
            ]);

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
