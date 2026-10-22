<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Requests\Api\Mobile\V1\Account\DeletePaymentMethodRequest;
use App\Http\Requests\Api\Mobile\V1\Account\IndexPaymentMethodRequest;
use App\Http\Requests\Api\Mobile\V1\Account\StorePaymentMethodRequest;
use App\Http\Resources\Mobile\V1\PaymentMethodResource;
use App\Http\Resources\Mobile\V1\StatusResource;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Services\Account\PaymentMethodService;
use Illuminate\Http\JsonResponse;

class PaymentMethodController extends ApiController
{
    public function index(IndexPaymentMethodRequest $request): JsonResponse
    {
        $customer = $request->user();

        if (! $customer instanceof Customer) {
            return $this->unauthorized();
        }

        $methods = app(PaymentMethodService::class)->listForCustomer($customer);

        return $this->success(PaymentMethodResource::collection($methods));
    }

    public function store(StorePaymentMethodRequest $request): JsonResponse
    {
        $customer = $request->user();

        if (! $customer instanceof Customer) {
            return $this->unauthorized();
        }

        $method = app(PaymentMethodService::class)->createForCustomer($customer, $request->validated());

        return $this->success(new PaymentMethodResource($method), null, 201);
    }

    public function destroy(DeletePaymentMethodRequest $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $customer = $request->user();

        if (! $customer instanceof Customer || $paymentMethod->customer_id !== $customer->id) {
            return $this->forbidden('Forbidden');
        }

        app(PaymentMethodService::class)->deleteForCustomer($customer, $paymentMethod);

        return $this->success(new StatusResource(['ok' => true]));
    }
}
