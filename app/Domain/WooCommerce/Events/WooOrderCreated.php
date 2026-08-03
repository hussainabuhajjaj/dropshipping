<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Events;

use App\Domain\Orders\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WooOrderCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly int $woocommerceOrderId,
        public readonly string $woocommerceOrderNumber,
    ) {
    }
}
