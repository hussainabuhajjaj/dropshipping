<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TokenController extends ApiController
{
    /**
     * Get all tokens for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()->get()->map(function ($token) {
            return [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at?->toISOString(),
                'expires_at' => $token->expires_at?->toISOString(),
                'created_at' => $token->created_at->toISOString(),
            ];
        });

        return $this->success($tokens);
    }

    /**
     * Create a new API token.
     */
    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['sometimes', 'array'],
            'abilities.*' => ['string'],
            'expires_in_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
        ]);

        $abilities = $validated['abilities'] ?? ['*'];
        $expiresAt = now()->addDays($validated['expires_in_days'] ?? 30);

        $token = $request->user()->createToken(
            $validated['name'],
            $abilities,
            $expiresAt
        );

        return $this->created([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'abilities' => $abilities,
            'expires_at' => $token->accessToken->expires_at?->toISOString(),
        ], 'Token created successfully');
    }

    /**
     * Revoke a specific token.
     */
    public function revoke(Request $request, string $id): JsonResponse
    {
        $token = $request->user()->tokens()->findOrFail($id);
        
        $token->delete();

        return $this->deleted('Token revoked successfully');
    }

    /**
     * Revoke all tokens except the current one.
     */
    public function revokeAll(Request $request): JsonResponse
    {
        $currentToken = $request->user()->currentAccessToken();
        
        $count = $request->user()->tokens()
            ->where('id', '!=', $currentToken->id)
            ->delete();

        return $this->success([
            'revoked_count' => $count,
        ], 'All other tokens revoked successfully');
    }
}
