<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Storefront\Auth\LoginRequest;
use App\Http\Requests\Api\Storefront\Auth\LogoutRequest;
use App\Http\Requests\Api\Storefront\Auth\RegisterRequest;
use App\Http\Requests\Api\Storefront\Auth\UserRequest;
use App\Http\Resources\Storefront\AuthResponseResource;
use App\Http\Resources\Storefront\CustomerResource;
use App\Http\Resources\Storefront\StatusResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
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

        $customer->save();

        $token = $customer->createToken(
            $data['device_name'] ?? 'mobile',
            ['*'],
            now()->addDays(30)
        );

        return (new AuthResponseResource([
            'user' => $customer,
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toISOString(),
        ]))->response()->setStatusCode(201);
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

        return new AuthResponseResource([
            'user' => $customer,
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toISOString(),
        ]);
    }

    public function logout(LogoutRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof Customer) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $token = $user->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return new StatusResource(['ok' => true]);
    }

    public function user(UserRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof Customer) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return new CustomerResource($user);
    }
}
