<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Domain\Common\Models\Address;
use App\Http\Requests\Api\Mobile\V1\Account\DeleteAddressRequest;
use App\Http\Requests\Api\Mobile\V1\Account\IndexAddressRequest;
use App\Http\Requests\Api\Mobile\V1\Account\StoreAddressRequest;
use App\Http\Requests\Api\Mobile\V1\Account\UpdateAddressRequest;
use App\Http\Resources\Mobile\V1\AddressResource;
use App\Http\Resources\Mobile\V1\StatusResource;
use App\Models\Customer;
use App\Services\Account\AddressService;
use Illuminate\Http\JsonResponse;

class AddressController extends ApiController
{
    public function index(IndexAddressRequest $request): JsonResponse
    {
        $customer = $request->user();

        if (! $customer instanceof Customer) {
            return $this->unauthorized();
        }

        $addresses = app(AddressService::class)->listForCustomer($customer);

        return $this->success(AddressResource::collection($addresses));
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        $customer = $request->user();

        if (! $customer instanceof Customer) {
            return $this->unauthorized();
        }

        $address = app(AddressService::class)->createForCustomer($customer, $request->validated());

        return $this->success(new AddressResource($address), null, 201);
    }

    public function update(UpdateAddressRequest $request, Address $address): JsonResponse
    {
        $customer = $request->user();

        if (! $customer instanceof Customer || $address->customer_id !== $customer->id) {
            return $this->forbidden('Forbidden');
        }

        $address = app(AddressService::class)->updateForCustomer($customer, $address, $request->validated());

        return $this->success(new AddressResource($address));
    }

    public function destroy(DeleteAddressRequest $request, Address $address): JsonResponse
    {
        $customer = $request->user();

        if (! $customer instanceof Customer || $address->customer_id !== $customer->id) {
            return $this->forbidden('Forbidden');
        }

        app(AddressService::class)->deleteForCustomer($customer, $address);

        return $this->success(new StatusResource(['ok' => true]));
    }
}
