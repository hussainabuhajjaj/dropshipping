<?php

declare(strict_types=1);

namespace App\Events\User;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CurrencyPreferenceChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly string $currency;
    public readonly ?int $userId;
    public readonly ?string $sessionId;

    public function __construct(
        string $currency,
        ?int $userId = null,
        ?string $sessionId = null
    ) {
        $this->currency = $currency;
        $this->userId = $userId ?? auth('customer')->id();
        $this->sessionId = $sessionId ?? session()->getId();
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->userId),
        ];
    }
}
