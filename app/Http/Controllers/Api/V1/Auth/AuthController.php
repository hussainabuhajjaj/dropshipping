<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // For token-based auth (mobile/external clients)
        if ($request->has('device_name')) {
            $token = $user->createToken(
                $request->device_name ?? 'default',
                ['*'], // Full access token
                now()->addDays(30)
            );

            return $this->created([
                'user' => new UserResource($user),
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $token->accessToken->expires_at?->toISOString(),
            ], 'User registered successfully');
        }

        // For SPA (cookie-based auth)
        Auth::login($user, $request->remember ?? false);

        return $this->created([
            'user' => new UserResource($user),
        ], 'User registered successfully');
    }

    /**
     * Login a user.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        // Rate limiting
        $key = 'login:' . $request->ip();
        
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            
            throw ValidationException::withMessages([
                'email' => ["Too many login attempts. Please try again in {$seconds} seconds."],
            ]);
        }

        // Attempt authentication
        if (! Auth::attempt($request->only('email', 'password'), $request->remember)) {
            RateLimiter::hit($key, 60); // Lock for 60 seconds
            
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Clear rate limiter on successful login
        RateLimiter::clear($key);

        $user = Auth::user();

        // For token-based auth (mobile/external clients)
        if ($request->has('device_name')) {
            // Revoke existing tokens for this device (optional)
            // $user->tokens()->where('name', $request->device_name)->delete();

            $token = $user->createToken(
                $request->device_name ?? 'default',
                ['*'], // Full access - you can customize abilities here
                now()->addDays(30)
            );

            return $this->success([
                'user' => new UserResource($user),
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $token->accessToken->expires_at?->toISOString(),
            ], 'Login successful');
        }

        // For SPA (cookie-based auth)
        $request->session()->regenerate();

        return $this->success([
            'user' => new UserResource($user),
        ], 'Login successful');
    }

    /**
     * Logout the authenticated user.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        // For token-based auth - revoke current token
        if ($request->bearerToken()) {
            $user->currentAccessToken()->delete();
            
            return $this->success(null, 'Token revoked successfully');
        }

        // For SPA - logout and invalidate session
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->success(null, 'Logged out successfully');
    }

    /**
     * Get the authenticated user.
     */
    public function user(Request $request): JsonResponse
    {
        return $this->success(new UserResource($request->user()));
    }

    /**
     * Update the authenticated user's profile.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,' . $request->user()->id],
        ]);

        if (isset($validated['email'])) {
            $validated['email'] = strtolower(trim($validated['email']));
            $validated['email_verified_at'] = null; // Require re-verification
        }

        $request->user()->update($validated);

        return $this->success(
            new UserResource($request->user()->fresh()),
            'Profile updated successfully'
        );
    }

    /**
     * Update the authenticated user's password.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        // Verify current password
        if (! Hash::check($validated['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided password does not match your current password.'],
            ]);
        }

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Optionally revoke all tokens except current
        if ($request->bearerToken()) {
            $currentToken = $request->user()->currentAccessToken();
            $request->user()->tokens()->where('id', '!=', $currentToken->id)->delete();
        }

        return $this->success(null, 'Password updated successfully');
    }
}
