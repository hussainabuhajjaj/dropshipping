<?php

namespace App\Models;

use App\Domain\Fulfillment\Models\FulfillmentJob as DomainFulfillmentJob;

class Fulfillment extends DomainFulfillmentJob
{
    protected $table = 'fulfillment_jobs';
}
