<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

class ModerationService
{
    public function __construct(private ?DeepSeekClient $client = null)
    {
    }

    /**
     * Return true when the content passes moderation.
     * Uses a simple local blacklist by default, and can optionally call DeepSeek when configured.
     *
     * @param array<int, string> $values
     */
    public function isAllowed(array $values): bool
    {
        $values = array_filter(array_map('trim', $values));
        if ($values === []) {
            return true;
        }

        // Local blacklist first
        $blacklist = array_filter(array_map('trim', (array) (config('ai.moderation.blacklist') ?? [])));
        if ($blacklist !== []) {
            $pattern = '/' . implode('|', array_map('preg_quote', $blacklist)) . '/i';
            foreach ($values as $v) {
                if (preg_match($pattern, $v)) {
                    return false;
                }
            }
        }

        // Optionally use provider-based moderation
        if (config('ai.moderation.use_deepseek') && $this->client) {
            try {
                $prompt = "Check the following text for policy violations. Return ONLY a JSON object {\"allowed\": true|false, \"reasons\": [..]}.\n\nText:\n" . implode("\n---\n", $values);

                $resp = $this->client->chat([
                    ['role' => 'system', 'content' => 'You assess content against safety/policy rules.'],
                    ['role' => 'user', 'content' => $prompt],
                ], 0.0);

                $decoded = json_decode($resp, true);
                if (is_array($decoded) && isset($decoded['allowed'])) {
                    return (bool) $decoded['allowed'];
                }

                // best effort: check for explicit tokens
                if (stripos($resp, 'allowed') !== false && stripos($resp, 'false') !== false) {
                    return false;
                }

                return true;
            } catch (\Throwable $e) {
                Log::warning('DeepSeek moderation failed, falling back to local checks', ['error' => $e->getMessage()]);
                return true; // fail open to avoid blocking traffic when moderation service is down
            }
        }

        return true;
    }
}
