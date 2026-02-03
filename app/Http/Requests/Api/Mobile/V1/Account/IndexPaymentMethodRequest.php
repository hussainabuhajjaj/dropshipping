<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Mobile\V1\Account;

use App\Http\Requests\Api\Mobile\V1\Concerns\FailsWithJson;

class IndexPaymentMethodRequest extends \App\Http\Requests\Api\Storefront\Payment\IndexPaymentMethodRequest
{
    use FailsWithJson;
}
