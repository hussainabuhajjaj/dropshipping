<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Storefront;

use App\Domain\Common\Models\Address;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Storefront\Address\DeleteAddressRequest;
use App\Http\Requests\Api\Storefront\Address\IndexAddressRequest;
use App\Http\Requests\Api\Storefront\Address\StoreAddressRequest;
use App\Http\Requests\Api\Storefront\Address\UpdateAddressRequest;
use App\Http\Resources\Storefront\AddressResource;
use App\Http\Resources\Storefront\ResourceCollection;
use App\Http\Resources\Storefront\StatusResource;
use App\Models\Customer;
use App\Services\Account\AddressService;

class AddressController extends Controller
{
    public function index(IndexAddressRequest $request): ResourceCollection
    {
        $user = $request->user();

        if (! $user instanceof Customer) {
            return new ResourceCollection(collect(), AddressResource::class);
        }

        $addresses = app(AddressService::class)->listForCustomer($user);

        return new ResourceCollection($addresses, AddressResource::class);
    }

    public function store(StoreAddressRequest $request): AddressResource
    {
        $user = $request->user();

        if (! $user instanceof Customer) {
            abort(401);
        }

        $address = app(AddressService::class)->createForCustomer($user, $request->validated());

        return new AddressResource($address);
    }

    public function update(UpdateAddressRequest $request, Address $address): AddressResource
    {
        $user = $request->user();

        if (! $user instanceof Customer || $address->customer_id !== $user->id) {
            abort(403);
        }

        $address = app(AddressService::class)->updateForCustomer($user, $address, $request->validated());

        return new AddressResource($address);
    }

    public function destroy(DeleteAddressRequest $request, Address $address): StatusResource
    {
        $user = $request->user();

        if (! $user instanceof Customer || $address->customer_id !== $user->id) {
            abort(403);
        }

        app(AddressService::class)->deleteForCustomer($user, $address);

        return new StatusResource(['ok' => true]);
    }
}
