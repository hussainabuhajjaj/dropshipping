<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Domain\Payments\PaymentService;
use App\Http\Requests\Api\Mobile\V1\Payments\KorapayInitRequest;
use App\Http\Requests\Api\Mobile\V1\Payments\KorapayVerifyRequest;
use App\Http\Resources\Mobile\V1\KorapayInitResource;
use App\Http\Resources\Mobile\V1\PaymentStatusResource;
use App\Models\Payment;
use App\Domain\Orders\Models\Order;
use Illuminate\Http\JsonResponse;

class PaymentController extends ApiController
{
    public function init(KorapayInitRequest $request, PaymentService $paymentService): JsonResponse
    {
        $data = $request->validated();
        $customer = $request->user();

        $order = Order::query()
            ->where('number', $data['order_number'])
            ->first();

        if (! $order || $order->customer_id !== $customer?->id) {
            return $this->notFound('Order not found');
        }

        $payment = Payment::query()
            ->where('order_id', $order->id)
            ->where('provider', 'korapay')
            ->latest('id')
            ->first();

        if (! $payment) {
            $payment = Payment::create([
                'order_id' => $order->id,
                'provider' => 'korapay',
                'status' => 'pending',
                'provider_reference' => null,
                'amount' => $order->grand_total,
                'currency' => $order->currency,
                'paid_at' => null,
                'meta' => [
                    'type' => 'mobile_init',
                ],
            ]);
        }

        $customerPayload = is_array($data['customer'] ?? null) ? $data['customer'] : [];
        $init = $paymentService->initializeKorapay($order, $payment, $customerPayload);

        return $this->success(new KorapayInitResource($init));
    }

    public function verify(KorapayVerifyRequest $request, PaymentService $paymentService): JsonResponse
    {
        $reference = $request->validated()['reference'];
        $payment = $paymentService->verifyKorapay($reference);

        return $this->success(new PaymentStatusResource([
            'payment_status' => $payment->status,
            'order_status' => $payment->order?->status,
        ]));
    }
}
