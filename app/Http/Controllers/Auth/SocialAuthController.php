<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class SocialAuthController extends Controller
{
    private const MOBILE_STATE = 'mobile';
    private const MOBILE_REDIRECT_URI = 'mobile://auth/social-callback';

    /**
     * @return RedirectResponse|SymfonyRedirectResponse
     */
    public function redirect(Request $request, string $provider)
    {
        $isMobile = $this->isMobileFlow($request);
        $provider = $this->normalizeProvider($provider);

        if (! $provider) {
            return $isMobile ? $this->mobileErrorRedirect('Unsupported social provider.') : abort(404);
        }

        if (! class_exists(\Laravel\Socialite\Facades\Socialite::class)) {
            return $isMobile ? $this->mobileErrorRedirect('Social sign-in is not configured yet.') : $this->missingProvider();
        }

        if (! $this->hasProviderConfig($provider)) {
            return $isMobile ? $this->mobileErrorRedirect('Social sign-in is not configured yet.') : $this->missingProvider();
        }

        $redirectAfter = $request->query('redirect');

        if ($redirectAfter) {
            session(['social_login_redirect' => $redirectAfter]);
        }

        $driver = \Laravel\Socialite\Facades\Socialite::driver($provider);

        if ($isMobile) {
            return $driver
                ->stateless()
                ->with(['state' => self::MOBILE_STATE])
                ->redirect();
        }

        return $driver->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $isMobile = $this->isMobileFlow($request);
        $provider = $this->normalizeProvider($provider);

        if (! $provider) {
            return $isMobile ? $this->mobileErrorRedirect('Unsupported social provider.') : abort(404);
        }

        if (! class_exists(\Laravel\Socialite\Facades\Socialite::class)) {
            return $isMobile ? $this->mobileErrorRedirect('Social sign-in is not configured yet.') : $this->missingProvider();
        }

        if (! $this->hasProviderConfig($provider)) {
            return $isMobile ? $this->mobileErrorRedirect('Social sign-in is not configured yet.') : $this->missingProvider();
        }

        try {
            $driver = \Laravel\Socialite\Facades\Socialite::driver($provider);
            $socialUser = $isMobile ? $driver->stateless()->user() : $driver->user();
            $customer = $this->resolveCustomer($provider, $socialUser);

            if ($isMobile) {
                return $this->mobileSuccessRedirect($provider, $customer->id);
            }

            Auth::guard('customer')->login($customer, true);

            $redirectUrl = session('social_login_redirect');

            if ($redirectUrl && ! str_contains($redirectUrl, 'auth/')) {
                session()->forget('social_login_redirect');

                return redirect()->to($redirectUrl);
            }

            return redirect()->intended(route('account.index', absolute: false));
        } catch (\Throwable $exception) {
            if ($isMobile) {
                return $this->mobileErrorRedirect('Social sign-in failed. Please try again.');
            }

            return $this->missingProvider('Social sign-in failed. Please try again.');
        }
    }

    private function normalizeProvider(string $provider): ?string
    {
        $provider = strtolower($provider);
        $supported = ['google', 'facebook', 'apple'];

        return in_array($provider, $supported, true) ? $provider : null;
    }

    private function hasProviderConfig(string $provider): bool
    {
        $config = config("services.{$provider}");
        if (! is_array($config) || empty($config['client_id']) || empty($config['redirect'])) {
            return false;
        }

        if ($provider === 'apple') {
            return ! empty($config['client_secret'])
                || (! empty($config['key_id']) && ! empty($config['team_id']) && ! empty($config['private_key']));
        }

        return ! empty($config['client_secret']);
    }

    private function isMobileFlow(Request $request): bool
    {
        return $request->boolean('mobile') || $request->string('state')->toString() === self::MOBILE_STATE;
    }

    private function resolveCustomer(string $provider, object $socialUser): Customer
    {
        $email = $socialUser->getEmail();

        if (! $email) {
            throw new \RuntimeException('Your social account did not provide an email.');
        }

        $email = strtolower(trim((string) $email));
        $name = trim((string) $socialUser->getName());
        $parts = preg_split('/\s+/', $name) ?: [];
        $first = array_shift($parts) ?: $email;
        $last = $parts ? implode(' ', $parts) : null;

        $customer = Customer::firstOrCreate(
            ['email' => $email],
            [
                'first_name' => $first,
                'last_name' => $last,
                'password' => Hash::make(Str::random(32)),
                'email_verified_at' => now(),
            ]
        );

        $metadata = is_array($customer->metadata ?? null) ? $customer->metadata : [];
        $socialAccounts = is_array($metadata['social_accounts'] ?? null) ? $metadata['social_accounts'] : [];
        $socialAccounts[$provider] = array_filter([
            'id' => $socialUser->getId(),
            'avatar' => $socialUser->getAvatar(),
            'linked_at' => now()->toIso8601String(),
        ], static fn ($value) => $value !== null && $value !== '');
        $metadata['social_accounts'] = $socialAccounts;

        if (! empty($socialUser->getAvatar()) && empty($metadata['avatar'])) {
            $metadata['avatar'] = $socialUser->getAvatar();
        }

        $customer->metadata = $metadata;

        if (! $customer->email_verified_at) {
            $customer->email_verified_at = now();
        }

        if (! $customer->first_name && ! $customer->last_name && $name !== '') {
            $customer->name = $name;
        }

        $customer->save();

        return $customer;
    }

    private function mobileSuccessRedirect(string $provider, int $customerId): RedirectResponse
    {
        $exchangeCode = Str::random(64);

        Cache::put(
            $this->mobileExchangeCacheKey($exchangeCode),
            [
                'customer_id' => $customerId,
                'provider' => $provider,
            ],
            now()->addMinutes(5)
        );

        return redirect()->away(self::MOBILE_REDIRECT_URI . '?' . http_build_query([
            'code' => $exchangeCode,
            'provider' => $provider,
        ]));
    }

    private function mobileErrorRedirect(string $message): RedirectResponse
    {
        return redirect()->away(self::MOBILE_REDIRECT_URI . '?' . http_build_query([
            'error' => $message,
        ]));
    }

    private function mobileExchangeCacheKey(string $code): string
    {
        return 'mobile-social-auth:' . hash('sha256', $code);
    }

    private function missingProvider(string $message = 'Social sign-in is not configured yet.'): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['social' => $message]);
    }
}
