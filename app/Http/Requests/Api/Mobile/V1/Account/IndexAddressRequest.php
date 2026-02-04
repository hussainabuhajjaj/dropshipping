<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Mobile\V1\Account;

use App\Http\Requests\Api\Mobile\V1\Concerns\FailsWithJson;

class IndexAddressRequest extends \App\Http\Requests\Api\Storefront\Address\IndexAddressRequest
{
    use FailsWithJson;
}
