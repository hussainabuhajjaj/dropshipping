<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Events\Orders\OrderCancellationRequested;
use App\Notifications\Orders\OrderCancellationConfirmedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user('customer');

        $perPage = 12;
        $orders = Order::query()
            ->where('customer_id', $user->id)
            ->latest('placed_at')
            ->paginate($perPage)
            ->through(function (Order $order) {
                return [
                    'id' => $order->id,
                    'number' => $order->number,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'grand_total' => $order->grand_total,
                    'currency' => $order->currency,
                    'placed_at' => $order->placed_at,
                    'email' => $order->email,
                ];
            });

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        $customer = $request->user('customer');
        if (! $customer || $order->customer_id !== $customer->id) {
            abort(404);
        }

        $order->load([
            'shippingAddress',
            'billingAddress',
            'orderItems.productVariant.product',
            'orderItems.review',
            'orderItems.returnRequest',
            'orderItems.shipments.trackingEvents',
            'payments',
        ]);

        return Inertia::render('Orders/Show', [
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'currency' => $order->currency,
                'subtotal' => $order->subtotal,
                'shipping_total' => $order->shipping_total,
                'tax_total' => $order->tax_total,
                'discount_total' => $order->discount_total,
                'grand_total' => $order->grand_total,
                'placed_at' => $order->placed_at,
                'delivery_notes' => $order->delivery_notes,
                'shippingAddress' => $order->shippingAddress ? [
                    'name' => $order->shippingAddress->name,
                    'line1' => $order->shippingAddress->line1,
                    'line2' => $order->shippingAddress->line2,
                    'city' => $order->shippingAddress->city,
                    'state' => $order->shippingAddress->state,
                    'postal_code' => $order->shippingAddress->postal_code,
                    'country' => $order->shippingAddress->country,
                    'phone' => $order->shippingAddress->phone,
                ] : null,
                'billingAddress' => $order->billingAddress ? [
                    'name' => $order->billingAddress->name,
                    'line1' => $order->billingAddress->line1,
                    'line2' => $order->billingAddress->line2,
                    'city' => $order->billingAddress->city,
                    'state' => $order->billingAddress->state,
                    'postal_code' => $order->billingAddress->postal_code,
                    'country' => $order->billingAddress->country,
                ] : null,
                'items' => $order->orderItems->map(function ($item) {
                    $product = $item->productVariant?->product;
                    return [
                        'id' => $item->id,
                        'name' => $item->snapshot['name'] ?? 'Item',
                        'variant' => $item->snapshot['variant'] ?? null,
                        'product_id' => $product?->id,
                        'product_slug' => $product?->slug,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'total' => $item->total,
                        'fulfillment_status' => $item->fulfillment_status,
                        'review' => $item->review ? [
                            'id' => $item->review->id,
                            'rating' => $item->review->rating,
                            'title' => $item->review->title,
                            'body' => $item->review->body,
                            'status' => $item->review->status,
                            'created_at' => $item->review->created_at,
                        ] : null,
                        'return_request' => $item->returnRequest ? [
                            'id' => $item->returnRequest->id,
                            'status' => $item->returnRequest->status,
                            'reason' => $item->returnRequest->reason,
                            'notes' => $item->returnRequest->notes,
                            'return_label_url' => $item->returnRequest->return_label_url,
                            'created_at' => $item->returnRequest->created_at,
                        ] : null,
                        'shipments' => $item->shipments->map(function ($shipment) {
                            return [
                                'id' => $shipment->id,
                                'tracking_number' => $shipment->tracking_number,
                                'carrier' => $shipment->carrier,
                                'tracking_url' => $shipment->tracking_url,
                                'shipped_at' => $shipment->shipped_at,
                                'delivered_at' => $shipment->delivered_at,
                                'events' => $shipment->trackingEvents->map(fn ($event) => [
                                    'id' => $event->id,
                                    'status_label' => $event->status_label,
                                    'description' => $event->description,
                                    'location' => $event->location,
                                    'occurred_at' => $event->occurred_at,
                                ]),
                            ];
                        }),
                    ];
                }),
                'payments' => $order->payments->map(fn ($payment) => [
                    'id' => $payment->id,
                    'provider' => $payment->provider,
                    'status' => $payment->status,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'provider_reference' => $payment->provider_reference,
                    'paid_at' => $payment->paid_at,
                ]),
            ],
        ]);
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        $customer = $request->user('customer');
        if (! $customer || $order->customer_id !== $customer->id) {
            abort(404);
        }

        // Only allow cancellation of orders that are not yet fulfilled or cancelled
        if (!in_array($order->status, ['pending', 'awaiting_fulfillment', 'fulfilling'])) {
            return response()->json([
                'error' => 'This order cannot be cancelled. It has already been fulfilled or cancelled.',
            ], 422);
        }

        // Only allow cancellation if payment has been confirmed
        if ($order->payment_status !== 'paid') {
            return response()->json([
                'error' => 'This order cannot be cancelled. Payment status does not allow cancellation.',
            ], 422);
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        return DB::transaction(function () use ($order, $customer, $data) {
            // Update order status
            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            // Process refund
            $payment = $order->payments()->where('status', 'completed')->first();
            if ($payment) {
                $refundAmount = $order->grand_total;
                $payment->update([
                    'refund_status' => 'pending',
                    'refund_amount' => $refundAmount,
                    'refunded_at' => now(),
                ]);
            }

            // Dispatch cancellation event
            event(new OrderCancellationRequested($order, $data['reason'] ?? null));

            // Send confirmation notification
            $notifiable = $order->customer ?? $order->user;
            if ($notifiable) {
                Notification::send($notifiable, new OrderCancellationConfirmedNotification($order, (string) $order->grand_total));
            } else {
                Notification::route('mail', $order->email)
                    ->notify(new OrderCancellationConfirmedNotification($order, (string) $order->grand_total));
            }

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully. A refund will be processed shortly.',
                'order_number' => $order->number,
            ]);
        });
    }

}
