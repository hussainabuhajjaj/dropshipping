<?php

declare(strict_types=1);

namespace App\Events\Orders;

use App\Models\ReturnRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReturnApproved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ReturnRequest $returnRequest,
        public ?string $returnLabelUrl = null,
    ) {
    }
}
