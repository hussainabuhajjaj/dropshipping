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

class LanguagePreferenceChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly string $language;
    public readonly ?int $userId;
    public readonly ?string $sessionId;

    public function __construct(
        string $language,
        ?int $userId = null,
        ?string $sessionId = null
    ) {
        $this->language = $language;
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
