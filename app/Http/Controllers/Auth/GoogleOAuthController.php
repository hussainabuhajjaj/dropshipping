<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\GoogleOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class GoogleOAuthController extends Controller
{
    public function __construct(
        private GoogleOAuthService $oauthService,
    ) {}

    /**
     * Redirect user to Google OAuth consent screen
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')
            ->scopes([
                'https://www.googleapis.com/auth/calendar',
                'https://www.googleapis.com/auth/userinfo.email',
                'https://www.googleapis.com/auth/userinfo.profile',
            ])
            ->with(['prompt' => 'consent'])
            ->redirect();
    }

    /**
     * Handle Google OAuth callback
     */
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            // Store tokens
            $this->oauthService->storeTokens([
                'access_token' => $googleUser->token,
                'refresh_token' => $googleUser->refreshToken,
            ]);

            // TODO: Map googleUser to your application user or create new user
            $user = \App\Models\User::where('email', $googleUser->email)->first();

            if (!$user) {
                // Create new user if doesn't exist (optional)
                $user = \App\Models\User::create([
                    'name' => $googleUser->name,
                    'email' => $googleUser->email,
                    'email_verified_at' => now(),
                    'password' => bcrypt(uniqid()),
                ]);
            }

            Auth::login($user);

            Log::info('User logged in via Google OAuth', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return redirect()->intended(route('dashboard'));
        } catch (\Throwable $e) {
            Log::error('Google OAuth callback failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('login')->withErrors(['google' => 'Failed to authenticate with Google']);
        }
    }

    /**
     * Refresh access token
     */
    public function refresh(): RedirectResponse
    {
        if ($this->oauthService->refreshAccessToken()) {
            Log::info('Google OAuth token refreshed successfully');
            return redirect()->route('dashboard')->with('success', 'Token refreshed');
        }

        Log::error('Failed to refresh Google OAuth token');
        return redirect()->route('dashboard')->withErrors(['google' => 'Failed to refresh token']);
    }

    /**
     * Get calendar events
     */
    public function getCalendarEvents(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (!$this->oauthService->isConfigured()) {
            return response()->json(['error' => 'Google OAuth not configured'], 400);
        }

        $calendarId = $request->query('calendar_id', 'primary');
        $maxResults = (int) $request->query('max_results', 10);

        $events = $this->oauthService->getCalendarEvents($calendarId, $maxResults);

        if ($events === null) {
            return response()->json(['error' => 'Failed to fetch calendar events'], 500);
        }

        $formattedEvents = array_map(fn ($event) => [
            'id' => $event->getId(),
            'summary' => $event->getSummary(),
            'description' => $event->getDescription(),
            'start' => $event->getStart()?->dateTime ?? $event->getStart()?->date,
            'end' => $event->getEnd()?->dateTime ?? $event->getEnd()?->date,
            'htmlLink' => $event->getHtmlLink(),
        ], $events);

        return response()->json([
            'events' => $formattedEvents,
            'count' => count($formattedEvents),
        ]);
    }

    /**
     * Logout and clear tokens
     */
    public function logout(Request $request): RedirectResponse
    {
        $this->oauthService->clearTokens();
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Log::info('User logged out and Google OAuth tokens cleared');

        return redirect()->route('home');
    }
}
