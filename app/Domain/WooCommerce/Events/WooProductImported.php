<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Events;

use App\Domain\Products\Models\Product;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WooProductImported
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Product $product,
        public readonly int $woocommerceProductId,
    ) {
    }
}
