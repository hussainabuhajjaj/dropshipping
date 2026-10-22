<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Mobile\V1\Account;

use App\Http\Requests\Api\Mobile\V1\Concerns\FailsWithJson;

class StorePaymentMethodRequest extends \App\Http\Requests\Api\Storefront\Payment\StorePaymentMethodRequest
{
    use FailsWithJson;

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'provider_ref' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
