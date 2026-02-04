<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Http\Resources\Mobile\V1\Concerns\WithoutSuccessWrapper;

use Illuminate\Http\Request;

class WalletResource extends \App\Http\Resources\Storefront\JsonResource
{
    use WithoutSuccessWrapper;
    public function toArray(Request $request): array
    {
        $data = is_array($this->resource) ? $this->resource : [];

        $giftCards = array_map(function ($card) {
            $expiresAt = $card['expires_at'] ?? null;

            return [
                'id' => $card['id'] ?? null,
                'code' => $card['code'] ?? null,
                'balance' => isset($card['balance']) ? (float) $card['balance'] : null,
                'currency' => $card['currency'] ?? 'USD',
                'status' => $card['status'] ?? null,
                'expires_at' => $expiresAt?->toIso8601String() ?? (is_string($expiresAt) ? $expiresAt : null),
            ];
        }, $data['gift_cards'] ?? []);

        return [
            'gift_cards' => $giftCards,
            'saved_coupons' => $data['saved_coupons'] ?? [],
            'available_coupons' => $data['available_coupons'] ?? [],
        ];
    }
}
