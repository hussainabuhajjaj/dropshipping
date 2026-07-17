<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Requests\Api\Mobile\V1\Auth\LoginRequest;
use App\Http\Requests\Api\Mobile\V1\Auth\RegisterRequest;
use App\Http\Requests\Api\Mobile\V1\Auth\SendPhoneOtpRequest;
use App\Http\Requests\Api\Mobile\V1\Auth\UpdateProfileRequest;
use App\Http\Requests\Api\Mobile\V1\Auth\VerifyPhoneOtpRequest;
use App\Http\Resources\Mobile\V1\AuthResponseResource;
use App\Http\Resources\Mobile\V1\CustomerResource;
use App\Http\Resources\Mobile\V1\StatusResource;
use App\Models\Customer;
use App\Services\Cart\CartIdentityService;
use App\Notifications\EmailVerificationOtpNotification;
use App\Notifications\PhoneVerificationOtpNotification;
use App\Notifications\PasswordResetOtpNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    public function __construct(
        private readonly CartIdentityService $cartIdentityService,
    ) {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $customer = new Customer();
        $customer->email = strtolower(trim($data['email']));
        $customer->phone = $data['phone'] ?? null;
        $customer->password = $data['password'];
        $customer->first_name = $data['first_name'] ?? null;
        $customer->last_name = $data['last_name'] ?? null;

        if (! $customer->first_name && ! $customer->last_name && ! empty($data['name'])) {
            $customer->name = $data['name'];
        }

        if (! empty($data['avatar'])) {
            $customer->metadata = array_merge($customer->metadata ?? [], [
                'avatar' => $data['avatar'],
            ]);
        }

        if (array_key_exists('marketing_opt_in', $data)) {
            $customer->marketing_opt_in = (bool) $data['marketing_opt_in'];
        }

        $customer->save();
        $this->dispatchEmailOtp($customer);

        $token = $customer->createToken(
            $data['device_name'] ?? 'mobile',
            ['*'],
            now()->addDays(30)
        );

        $this->cartIdentityService->mergeGuestCartIntoCustomer($request, $customer);

        return $this->created(new AuthResponseResource([
            'user' => $customer,
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toISOString(),
        ]));
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $customer = Customer::query()->where('email', strtolower(trim($data['email'])))->first();

        if (! $customer || ! Hash::check($data['password'], $customer->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $customer->createToken(
            $data['device_name'] ?? 'mobile',
            ['*'],
            now()->addDays(30)
        );

        $this->cartIdentityService->mergeGuestCartIntoCustomer($request, $customer);

        return $this->success(new AuthResponseResource([
            'user' => $customer,
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toISOString(),
        ]));
    }

    public function exchangeSocialCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'min:32'],
            'provider' => ['required', 'string', 'in:google,facebook,apple'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $cacheKey = $this->mobileExchangeCacheKey($data['code']);
        $cached = Cache::pull($cacheKey);

        if (! is_array($cached) || ($cached['provider'] ?? null) !== $data['provider']) {
            throw ValidationException::withMessages([
                'code' => ['This social sign-in session has expired. Please try again.'],
            ]);
        }

        $customer = Customer::query()->find($cached['customer_id'] ?? null);

        if (! $customer) {
            throw ValidationException::withMessages([
                'code' => ['We could not find the account for this social sign-in.'],
            ]);
        }

        $token = $customer->createToken(
            $data['device_name'] ?? 'mobile',
            ['*'],
            now()->addDays(30)
        );

        $this->cartIdentityService->mergeGuestCartIntoCustomer($request, $customer);

        return $this->success(new AuthResponseResource([
            'user' => $customer,
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toISOString(),
        ]));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return $this->success(new StatusResource(['ok' => true]));
    }

    public function me(Request $request): JsonResponse
    {

        return $this->success(new CustomerResource($request->user()));
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $customer = $request->user();
        $validated = $request->validated();
        $emailChanged = false;

        if (array_key_exists('email', $validated)) {
            $validated['email'] = strtolower(trim((string) $validated['email']));
            $emailChanged = $validated['email'] !== $customer->email;
            if ($emailChanged) {
                $validated['email_verified_at'] = null;
            }
        }

        if (array_key_exists('phone', $validated)) {
            if ($validated['phone'] !== $customer->phone) {
                $validated['phone_verified_at'] = null;
                $validated['phone_verification_code'] = null;
                $validated['phone_verification_expires_at'] = null;
            }
        }

        if (array_key_exists('avatar', $validated)) {
            $metadata = is_array($customer->metadata ?? null) ? $customer->metadata : [];
            if ($validated['avatar']) {
                $metadata['avatar'] = $validated['avatar'];
            } else {
                unset($metadata['avatar']);
            }
            $customer->metadata = $metadata;
            unset($validated['avatar']);
        }

        if (! empty($validated['name'])) {
            $customer->name = $validated['name'];
            unset($validated['name']);
        }

        $customer->fill($validated);
        $customer->save();

        if ($emailChanged) {
            $this->dispatchEmailOtp($customer);
        }

        return $this->success(new CustomerResource($customer->fresh()));
    }

    public function sendPhoneOtp(SendPhoneOtpRequest $request): JsonResponse
    {
        $customer = $request->user();
        $data = $request->validated();

        if (! empty($data['phone'])) {
            $customer->phone = $data['phone'];
            $customer->phone_verified_at = null;
        }

        if (! $customer->phone) {
            throw ValidationException::withMessages([
                'phone' => ['Phone number is required to verify.'],
            ]);
        }

        $this->dispatchPhoneOtp($customer);

        return $this->success(new StatusResource(['ok' => true]));
    }

    public function verifyPhoneOtp(VerifyPhoneOtpRequest $request): JsonResponse
    {
        $customer = $request->user();
        $data = $request->validated();

        if (! $customer->phone_verification_code || ! $customer->phone_verification_expires_at) {
            throw ValidationException::withMessages([
                'code' => ['No active verification code. Please request a new one.'],
            ]);
        }

        if (now()->greaterThan($customer->phone_verification_expires_at)) {
            throw ValidationException::withMessages([
                'code' => ['Verification code expired. Please request a new one.'],
            ]);
        }

        if (! Hash::check($data['code'], $customer->phone_verification_code)) {
            throw ValidationException::withMessages([
                'code' => ['Invalid verification code.'],
            ]);
        }

        $customer->forceFill([
            'phone_verified_at' => now(),
            'phone_verification_code' => null,
            'phone_verification_expires_at' => null,
        ])->save();

        return $this->success(new CustomerResource($customer->fresh()));
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $customer = $request->user();

        if ($customer) {
            $this->dispatchEmailOtp($customer);
        }

        return $this->success(new StatusResource(['ok' => true]));
    }

    public function verifyEmailOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:4'],
        ]);

        $customer = $request->user();

        if (! $customer->email_verification_code || ! $customer->email_verification_expires_at) {
            throw ValidationException::withMessages([
                'code' => ['No active verification code. Please request a new one.'],
            ]);
        }

        if (now()->greaterThan($customer->email_verification_expires_at)) {
            throw ValidationException::withMessages([
                'code' => ['Verification code expired. Please request a new one.'],
            ]);
        }

        if (! Hash::check($data['code'], $customer->email_verification_code)) {
            throw ValidationException::withMessages([
                'code' => ['Invalid verification code.'],
            ]);
        }

        $customer->forceFill([
            'email_verified_at' => now(),
            'email_verification_code' => null,
            'email_verification_expires_at' => null,
        ])->save();

        return $this->success(new CustomerResource($customer->fresh()));
    }

    private function dispatchEmailOtp(Customer $customer): void
    {
        $code = (string) random_int(1000, 9999);

        $customer->forceFill([
            'email_verification_code' => Hash::make($code),
            'email_verification_expires_at' => now()->addMinutes(10),
        ])->save();

        try {
            $customer->notify(new EmailVerificationOtpNotification($code));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send email OTP notification', [
                'customer_id' => $customer->id,
                'email' => $customer->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        $customer = $request->user();

        if (! $customer instanceof Customer) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $customer->tokens()->delete();
        $customer->delete();

        return $this->success(new StatusResource(['ok' => true]));
    }

    private function dispatchPhoneOtp(Customer $customer): void
    {
        $code = (string) random_int(1000, 9999);

        $customer->forceFill([
            'phone_verification_code' => Hash::make($code),
            'phone_verification_expires_at' => now()->addMinutes(10),
        ])->save();

        $customer->notify(new PhoneVerificationOtpNotification($code, $customer->phone));
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'exists:customers,email'],
        ]);

        $customer = Customer::where('email', $data['email'])->first();

        if ($customer) {
            $this->dispatchPasswordResetOtp($customer);
        }

        return $this->success(new StatusResource(['ok' => true]));
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'exists:customers,email'],
            'token' => ['required', 'string', 'size:4'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $customer = Customer::where('email', $data['email'])->first();

        if (!$customer || !$customer->password_reset_code || !$customer->password_reset_expires_at) {
            throw ValidationException::withMessages([
                'token' => ['No active reset code. Please request a new one.'],
            ]);
        }

        if (now()->greaterThan($customer->password_reset_expires_at)) {
            throw ValidationException::withMessages([
                'token' => ['Reset code expired. Please request a new one.'],
            ]);
        }

        if (!Hash::check($data['token'], $customer->password_reset_code)) {
            throw ValidationException::withMessages([
                'token' => ['Invalid reset code.'],
            ]);
        }

        $customer->forceFill([
            'password' => Hash::make($data['password']),
            'password_reset_code' => null,
            'password_reset_expires_at' => null,
        ])->save();

        // Revoke all existing tokens to force re-login
        $customer->tokens()->delete();

        return $this->success(new StatusResource(['ok' => true]));
    }

    private function dispatchPasswordResetOtp(Customer $customer): void
    {
        $code = (string) random_int(1000, 9999);

        $customer->forceFill([
            'password_reset_code' => Hash::make($code),
            'password_reset_expires_at' => now()->addMinutes(10),
        ])->save();

        $customer->notify(new PasswordResetOtpNotification($code));
    }

    private function mobileExchangeCacheKey(string $code): string
    {
        return 'mobile-social-auth:' . hash('sha256', $code);
    }
}
