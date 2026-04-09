<?php

declare(strict_types=1);

namespace App\Events\Storefront;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StorefrontAnnouncementPushed implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $message,
        public readonly string $level = 'warning',
        public readonly bool $dismissible = true,
        public readonly ?string $id = null,
    ) {
    }

    public function broadcastOn(): Channel
    {
        // Public channel: all storefront browsers can subscribe without auth.
        return new Channel('storefront.announcements');
    }

    public function broadcastAs(): string
    {
        return 'storefront.announcement';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
            'level' => $this->level,
            'dismissible' => $this->dismissible,
            'id' => $this->id,
            'at' => now()->toISOString(),
        ];
    }
}

