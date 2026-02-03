<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Storefront\Order\TrackOrderRequest;
use App\Http\Resources\Storefront\OrderTrackingResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

class TrackingController extends Controller
{
    public function __invoke(TrackOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $orderNumber = $validated['number'];
        $email = $validated['email'];

        $order = Order::query()
            ->where('number', $orderNumber)
            ->where('email', $email)
            ->with(['orderItems.shipments.trackingEvents', 'events'])
            ->first();

        if (! $order) {
            return response()->json(['error' => 'Order not found.'], 404);
        }

        return response()->json(new OrderTrackingResource($order));
    }
}
