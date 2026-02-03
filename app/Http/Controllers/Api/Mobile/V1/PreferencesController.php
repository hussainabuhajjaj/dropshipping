<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Requests\Api\Mobile\V1\Preferences\PreferencesUpdateRequest;
use App\Http\Resources\Mobile\V1\PreferencesLookupsResource;
use App\Http\Resources\Mobile\V1\PreferencesResource;
use App\Models\Customer;
use App\Services\Account\PreferencesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PreferencesController extends ApiController
{
    public function lookups(Request $request): JsonResponse
    {
        $lookups = app(PreferencesService::class)->lookups();

        return $this->success(new PreferencesLookupsResource($lookups));
    }

    public function show(Request $request): JsonResponse
    {
        $customer = $request->user();
        if (! $customer instanceof Customer) {
            return $this->unauthorized();
        }

        $prefs = app(PreferencesService::class)->getPreferences($customer);

        return $this->success(new PreferencesResource($prefs));
    }

    public function update(PreferencesUpdateRequest $request): JsonResponse
    {
        $customer = $request->user();
        if (! $customer instanceof Customer) {
            return $this->unauthorized();
        }

        $prefs = app(PreferencesService::class)->updatePreferences($customer, $request->validated());

        return $this->success(new PreferencesResource($prefs));
    }
}
