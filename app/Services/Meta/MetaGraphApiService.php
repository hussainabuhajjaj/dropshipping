<?php

declare(strict_types=1);

namespace App\Services\Meta;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaGraphApiService
{
    private string $accessToken;

    private string $apiVersion;

    public function __construct()
    {
        $this->accessToken = (string) config('services.meta.page_access_token', '');
        $this->apiVersion = (string) config('services.meta.api_version', 'v21.0');
    }

    public function sendCommentReply(string $commentId, string $text): bool
    {
        if ($this->accessToken === '') {
            Log::warning('Meta comment reply skipped: no page access token configured');
            return false;
        }

        $response = Http::timeout(10)
            ->withQueryParameters([
                'access_token' => $this->accessToken,
                'message' => $text,
            ])
            ->post("https://graph.facebook.com/{$this->apiVersion}/{$commentId}/replies");

        if ($response->failed()) {
            Log::warning('Meta comment reply failed', [
                'comment_id' => $commentId,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return false;
        }

        return true;
    }

    public function sendInstagramPrivateReply(string $recipientId, string $text): bool
    {
        if ($this->accessToken === '') {
            Log::warning('Meta DM reply skipped: no page access token configured');
            return false;
        }

        $instagramAccountId = (string) config('services.meta.instagram_business_account_id');

        $response = Http::timeout(10)
            ->withQueryParameters(['access_token' => $this->accessToken])
            ->post(
                "https://graph.facebook.com/{$this->apiVersion}/{$instagramAccountId}/messages",
                [
                    'recipient' => ['id' => $recipientId],
                    'message' => ['text' => $text],
                ],
            );

        if ($response->failed()) {
            Log::warning('Meta DM reply failed', [
                'recipient_id' => $recipientId,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return false;
        }

        return true;
    }
}
