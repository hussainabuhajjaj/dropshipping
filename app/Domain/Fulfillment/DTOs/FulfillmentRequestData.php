<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\DTOs;

use App\Domain\Common\Models\Address;
use App\Domain\Fulfillment\Models\FulfillmentProvider;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Products\Models\SupplierProduct;

class FulfillmentRequestData
{
    public function __construct(
        public ?int                    $order_id,
        public ?array              $order_items = [],
        public ?OrderItem          $orderItem = null,
        public FulfillmentProvider $provider,
        public ?SupplierProduct    $supplierProduct = null,
        public ?Address            $shippingAddress = null,
        public ?Address            $billingAddress = null,
        public array               $options = [],
    )
    {
    }
}
