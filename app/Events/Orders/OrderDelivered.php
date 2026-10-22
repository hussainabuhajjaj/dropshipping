<?php

declare(strict_types=1);

namespace App\Events\Orders;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderDelivered
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Order $order, public ?string $deliveredAt = null)
    {
    }
}
