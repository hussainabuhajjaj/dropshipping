<?php

declare(strict_types=1);

namespace App\Services;

use Google\Client as GoogleClient;
use Google\Service\Calendar as GoogleCalendar;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class GoogleOAuthService
{
    private const CREDENTIALS_PATH = 'google/oauth-credentials.json';
    private const TOKEN_PATH = 'google/oauth-token.json';
    private const REFRESH_TOKEN_PATH = 'google/oauth-refresh-token.json';

    /**
     * Store OAuth tokens from Socialite user
     */
    public function storeTokens(array $tokens): void
    {
        if (isset($tokens['access_token'])) {
            Storage::disk('local')->put(self::TOKEN_PATH, $tokens['access_token']);
        }

        if (isset($tokens['refresh_token'])) {
            Storage::disk('local')->put(self::REFRESH_TOKEN_PATH, $tokens['refresh_token']);
        }
    }

    /**
     * Get stored access token
     */
    public function getAccessToken(): ?string
    {
        if (Storage::disk('local')->exists(self::TOKEN_PATH)) {
            return Storage::disk('local')->get(self::TOKEN_PATH);
        }

        return null;
    }

    /**
     * Get stored refresh token
     */
    public function getRefreshToken(): ?string
    {
        if (Storage::disk('local')->exists(self::REFRESH_TOKEN_PATH)) {
            return Storage::disk('local')->get(self::REFRESH_TOKEN_PATH);
        }

        return null;
    }

    /**
     * Refresh access token using refresh token
     */
    public function refreshAccessToken(): bool
    {
        try {
            $refreshToken = $this->getRefreshToken();

            if (!$refreshToken) {
                Log::warning('No refresh token found for Google OAuth');
                return false;
            }

            $client = $this->createClient();
            $newTokens = $client->fetchAccessTokenWithRefreshToken($refreshToken);

            if (isset($newTokens['access_token'])) {
                Storage::disk('local')->put(self::TOKEN_PATH, $newTokens['access_token']);
            }

            if (isset($newTokens['refresh_token'])) {
                Storage::disk('local')->put(self::REFRESH_TOKEN_PATH, $newTokens['refresh_token']);
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to refresh Google access token', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Create Google Client instance
     */
    public function createClient(): GoogleClient
    {
        $client = new GoogleClient();

        $credentialsPath = Storage::disk('local')->path(self::CREDENTIALS_PATH);

        if (file_exists($credentialsPath)) {
            $client->setAuthConfig($credentialsPath);
        }

        $accessToken = $this->getAccessToken();
        if ($accessToken) {
            $client->setAccessToken($accessToken);
        }

        return $client;
    }

    /**
     * Ensure access token is valid, refresh if needed
     */
    public function ensureValidToken(): bool
    {
        $client = $this->createClient();

        if ($client->isAccessTokenExpired()) {
            return $this->refreshAccessToken();
        }

        return true;
    }

    /**
     * Get Google Calendar service instance
     */
    public function getCalendarService(): ?GoogleCalendar
    {
        if (!$this->ensureValidToken()) {
            Log::warning('Could not ensure valid Google OAuth token');
            return null;
        }

        $client = $this->createClient();
        $client->addScope(GoogleCalendar::CALENDAR_READONLY);

        return new GoogleCalendar($client);
    }

    /**
     * Get calendar events
     */
    public function getCalendarEvents(string $calendarId = 'primary', int $maxResults = 10): ?array
    {
        try {
            $service = $this->getCalendarService();

            if (!$service) {
                return null;
            }

            $results = $service->events->listEvents($calendarId, [
                'maxResults' => $maxResults,
                'orderBy' => 'startTime',
                'singleEvents' => true,
                'timeMin' => date('c'),
            ]);

            return $results->getItems() ?? [];
        } catch (\Throwable $e) {
            Log::error('Failed to fetch Google Calendar events', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Check if credentials are configured
     */
    public function isConfigured(): bool
    {
        $credentialsPath = Storage::disk('local')->path(self::CREDENTIALS_PATH);
        return file_exists($credentialsPath) && !empty($this->getAccessToken());
    }

    /**
     * Clear stored tokens
     */
    public function clearTokens(): void
    {
        Storage::disk('local')->delete([
            self::TOKEN_PATH,
            self::REFRESH_TOKEN_PATH,
        ]);
    }
}
