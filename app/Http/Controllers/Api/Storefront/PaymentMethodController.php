<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Storefront\Payment\DeletePaymentMethodRequest;
use App\Http\Requests\Api\Storefront\Payment\IndexPaymentMethodRequest;
use App\Http\Requests\Api\Storefront\Payment\StorePaymentMethodRequest;
use App\Http\Resources\Storefront\PaymentMethodResource;
use App\Http\Resources\Storefront\ResourceCollection;
use App\Http\Resources\Storefront\StatusResource;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Services\Account\PaymentMethodService;

class PaymentMethodController extends Controller
{
    public function index(IndexPaymentMethodRequest $request): ResourceCollection
    {
        $user = $request->user();

        if (! $user instanceof Customer) {
            return new ResourceCollection(collect(), PaymentMethodResource::class);
        }

        $methods = app(PaymentMethodService::class)->listForCustomer($user);

        return new ResourceCollection($methods, PaymentMethodResource::class);
    }

    public function store(StorePaymentMethodRequest $request): PaymentMethodResource
    {
        $user = $request->user();

        if (! $user instanceof Customer) {
            abort(401);
        }

        $method = app(PaymentMethodService::class)->createForCustomer($user, $request->validated());

        return new PaymentMethodResource($method);
    }

    public function destroy(DeletePaymentMethodRequest $request, PaymentMethod $paymentMethod): StatusResource
    {
        $user = $request->user();

        if (! $user instanceof Customer || $paymentMethod->customer_id !== $user->id) {
            abort(403);
        }

        app(PaymentMethodService::class)->deleteForCustomer($user, $paymentMethod);

        return new StatusResource(['ok' => true]);
    }
}
