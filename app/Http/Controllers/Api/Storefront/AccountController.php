<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Storefront\Account\DeleteAccountRequest;
use App\Http\Requests\Api\Storefront\Account\ProfileRequest;
use App\Http\Requests\Api\Storefront\Account\UpdatePasswordRequest;
use App\Http\Requests\Api\Storefront\Account\UpdateProfileRequest;
use App\Http\Resources\Storefront\CustomerResource;
use App\Http\Resources\Storefront\StatusResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function profile(ProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof Customer) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return new CustomerResource($user);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof Customer) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validated();

        if (array_key_exists('email', $validated) && $validated['email']) {
            $validated['email'] = strtolower(trim($validated['email']));
        }

        if (! empty($validated['name'])) {
            $user->name = $validated['name'];
            unset($validated['name']);
        }

        $user->fill($validated);
        $user->save();

        return new CustomerResource($user->fresh());
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof Customer) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validated();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided password does not match your current password.'],
            ]);
        }

        $user->password = $validated['password'];
        $user->save();

        return new StatusResource(['ok' => true]);
    }

    public function delete(DeleteAccountRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof Customer) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user->tokens()->delete();
        $user->delete();

        return new StatusResource(['ok' => true]);
    }
}
