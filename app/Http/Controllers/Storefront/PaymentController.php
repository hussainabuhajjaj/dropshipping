<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\Storefront\Payment\CardResource;
use App\Http\Resources\User\CartResource;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\SiteSetting;
use App\Services\Payments\KorapayService;
use App\Services\Payments\PaymentResultService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use function PHPUnit\Framework\isNumeric;

class PaymentController extends Controller
{
    protected $korapayService;

    public function __construct(KorapayService $korapayService)
    {
        $this->korapayService = $korapayService;
    }

    public function index($type, $id = null)
    {

        $customer = auth('customer')->user();
        $item = $this->getItem($type, $id);
        if (!$item) {
            if ($type == "cart") {
                if (!isset($item) || !isset($item->items) || !$item->items->count()) {
                    return redirect()->route('products.index');
                }
            } else {
                abort(404);
            }
        }

        $summery = [];
        $items = [];
        if ($item instanceof Cart) {
            $summery = $item->getSummery();
            $items = (CartResource::collection($item->items))->jsonSerialize();
        }

        $final_total = @$summery['total'] ?? 0;

        $defaultAddress = isset($customer) ? $customer?->addresses()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first() : null;

        $addresses = isset($customer) ? $customer?->addresses()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get() : collect();


        return Inertia::render('Payments/Index', [
            'customer' => $customer,
            'defaultAddress' => $defaultAddress,
            'addresses' => $addresses->map(fn($address) => [
                'id' => $address->id,
                'name' => $address->name,
                'phone' => $address->phone,
                'line1' => $address->line1,
                'line2' => $address->line2,
                'city' => $address->city,
                'state' => $address->state,
                'postal_code' => $address->postal_code,
                'country' => $address->country,
                'is_default' => $address->is_default,
            ])->values()->all(),
            'type' => $type,
            'id' => $id,
            'summery' => $summery,
            'final_total' => $final_total,
            'items' => $items,
            'successMessage' => session('success'),
            'errorMessage' => session('error'),
            'errors' => session('errors') ? session('errors')->toArray() : (object)[]
        ]);

    }

    public function checkout(Request $request, $type, $id = null)
    {
        $item = $this->getItem($type, $id);
        if (!$item) {
            if ($type == "cart") {
                if (!$item || !$item || !$item->count()) {
                    return redirect()->route('products.index');
                }
            } else {
                return response()->json([
                    'status' => false,
                    'message' => "Item not found",
                ]);
            }
        }

        $summery = [];
        if ($item instanceof Cart) {
            $summery = $item->getSummery();
        }


        $extra_validation_rules = $this->getItemValidationArray($type);
        $request->validate(array_merge([
            'method' => 'required|in:card,mobile_money',
        ], $extra_validation_rules));

        $final_total = @$summery['total'] ?? 0;

        $method = $request->input('method');

        if (in_array($method, ['card', 'mobile_money'])) {
            session()->put('request_body', $request->all());
            $checkout = $this->korapayService->initializePayment($final_total, $method);

            if (isset($checkout['data'])) {
                session()->put('reference', $checkout['data']['reference']);
                return response()->json([
                    'status' => true,
                    'data' => [
                        'redirect' => $checkout['data']['checkout_url']
                    ]
                ]);
            }
        }

    }

    public function redirect(PaymentResultService $paymentResultService, Request $request, $type, $id = null)
    {
        $item = $this->getItem($type, $id);

        $reference = (string)($request->input('reference')
            ?? $request->input('payment_reference')
            ?? $request->input('transaction_reference')
            ?? $request->query('trxref')
            ?? '');

        if (!$reference) {
            abort(404);
        }
        $verify_result = $this->korapayService->checkStatus($reference);

        if (isset($verify_result) && $verify_result['status']) {

            $payment_result = strtolower((string)($verify_result['data']['status'] ?? ''));

            if ($payment_result == "success") {
                $existingPayment = Payment::query()
                    ->where('provider', 'korapay')
                    ->where('provider_reference', $reference)
                    ->with('order')
                    ->latest('id')
                    ->first();

                if ($existingPayment?->order) {
                    return redirect()->route('orders.confirmation', ['number' => $existingPayment->order->number]);
                }

                if (!$item) {
                    if ($type === "cart") {
                        return redirect()->route('products.index');
                    }

                    return redirect('/')->withErrors([
                        "Item not found"
                    ]);
                }

                // do register payment
                $result =  $paymentResultService->registerCompletePayment($item, $verify_result);
                Log::info('result : ' . json_encode($result));
                return redirect()->away($result);
                return redirect($result);
            } else {
                return $paymentResultService->registerFailedPayment($type, $id, $verify_result);
                // do failed payment
            }
        } else {
            return $paymentResultService->registerFailedPayment($type, $id, $verify_result);
        }

    }


    private function getItem($type, $id)
    {
        if ($type == "order") {
            return Order::query()->findOrFail($id);
        } elseif ($type == "cart") {
            return Cart::query()->where('user_id', \auth('customer')->id())
                ->orWhere('session_id', session()->id())
                ->with('items')
                ->first();
        }
    }

    private function getItemValidationArray($type)
    {
        if ($type == "order") {
            return [];
        } elseif ($type == "cart") {
            $address_id = \request()->get('address_id');
            if (isset($address_id) && isnumeric($address_id)) {
                return [
                    'address_id' => 'required|numeric|exists:addresses,id',
                ];
            } else {
                return [
                    'email' => ['required', 'email'],
                    'phone' => ['required', 'string', 'max:30'],
                    'first_name' => ['required', 'string', 'max:120'],
                    'last_name' => ['nullable', 'string', 'max:120'],
                    'line1' => ['required', 'string', 'max:255'],
                    'line2' => ['nullable', 'string', 'max:255'],
                    'city' => ['required', 'string', 'max:120'],
                    'state' => ['nullable', 'string', 'max:120'],
                    'postal_code' => ['nullable', 'string', 'max:30'],
                    'country' => ['required', 'string', 'max:2'],
                    'delivery_notes' => ['nullable', 'string', 'max:500'],
                ];
            }

        }
    }
}
