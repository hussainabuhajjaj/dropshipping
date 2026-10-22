<?php

declare(strict_types=1);

namespace App\Listeners\Orders;

use App\Domain\Orders\Services\LinehaulShipmentService;
use App\Events\Orders\OrderPaid;
use App\Events\Orders\OrderPlaced;

class MaterializeLinehaulShipment
{
    public function __construct(private readonly LinehaulShipmentService $linehaulShipments)
    {
    }

    public function handle(OrderPlaced|OrderPaid $event): void
    {
        $this->linehaulShipments->createOrUpdateFromQuote($event->order);
    }
}
