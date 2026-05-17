<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppOrderIntent;
use App\Services\Checkout\WhatsAppOrderIntentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class WhatsAppOrderIntentController extends Controller
{
    public function __construct(
        private readonly WhatsAppOrderIntentService $service,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'mode' => ['required', 'in:product,cart'],
            'product_id' => ['nullable', 'integer'],
            'variant_id' => ['nullable', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'channel' => ['nullable', 'in:web,mobile'],
            'phone' => ['nullable', 'string', 'max:30'],
            'guest_token' => ['nullable', 'string', 'max:120'],
        ]);

        if (($payload['mode'] ?? null) === 'product' && empty($payload['product_id'])) {
            return response()->json(['message' => 'product_id is required for product mode.'], 422);
        }

        try {
            $intent = $this->service->create($request, $payload);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($this->service->buildResponsePayload($intent), 201);
    }

    public function show(string $reference): JsonResponse
    {
        $intent = $this->service->findByReference($reference);

        return response()->json($this->service->buildResponsePayload($intent));
    }

    public function convert(Request $request, string $reference): JsonResponse
    {
        $intent = $this->service->findByReference($reference);
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['required', 'string', 'max:30'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country' => ['required', 'string', 'size:2'],
            'delivery_notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $adminId = auth(config('filament.auth.guard', 'admin'))->id() ?? auth()->id();
            $order = $this->service->convert($intent, $payload, $adminId);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'reference' => $intent->reference,
            'status' => $intent->fresh()->status,
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
            ],
        ]);
    }

    public function expire(string $reference): JsonResponse
    {
        /** @var WhatsAppOrderIntent $intent */
        $intent = $this->service->findByReference($reference);
        $intent->markExpired('manually_expired');

        return response()->json([
            'reference' => $intent->reference,
            'status' => $intent->fresh()->status,
        ]);
    }
}
