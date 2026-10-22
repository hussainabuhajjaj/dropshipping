<?php

namespace App\Models;

use App\Domain\Fulfillment\Models\FulfillmentProvider;

class Supplier extends FulfillmentProvider
{
    protected $table = 'fulfillment_providers';
}
