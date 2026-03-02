<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\Storefront\Payment\CardResource;
use App\Http\Resources\User\CartResource;
use App\Models\Cart;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\SiteSetting;
use App\Services\Payments\KorapayService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaymentController extends Controller
{
    protected $korapayService;

    public function __construct(KorapayService $korapayService)
    {
        $this->korapayService = $korapayService;
    }

    public function index($type, $id)
    {
        $item = $this->getItem($type, $id);
        if (!$item) {
            if ($type == "cart") {
                if (!$item || !$item || !$item->count()) {
                    return redirect()->route('products.index');
                }
            } else {
                abort(404);
            }
        }

        $summery = [];
        if ($item instanceof Cart) {
            $summery = $item->getSummery();
            $items = (CartResource::collection($item->items))->jsonSerialize();
        }


        $final_total = @$summery['total'] ?? 0;

        //        $data = [
//            'classType' => $type,
//            'id' => $id,
//            'price' => $orderData['price'] ?? 0,
//            'orderItems' => $orderData['items'] ?? [],
//            'locales' => ['ar', 'en'],
//            'successMessage' => session('success'),
//            'errorMessage' => session('error'),
//            'errors' => session('errors') ? session('errors')->toArray() : (object)[]
//        ];


        return Inertia::render('Payments/Index', [
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

    public function checkout($type, $id, Request $request)
    {
        $item = $this->getItem($type, $id);
        $amount = $this->getTotal($item);
        $customer = auth('customer')->user();
//        return back()->withErrors([
//            'card_number' => 'Invalid card number'
//        ]);


        $request->validate([
            'method' => 'required|in:card,mobile_money',
        ]);

        $method = $request->get('method');

        if (in_array($method, ['card', 'mobile_money'])) {
            $checkout = $this->korapayService->initializePayment($amount, $method);

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

        dd($item, $amount, $request->all());
    }

    public function redirect($type, $id, Request $request)
    {
        $item = $this->getItem($type, $id);

        $amount = $item['grand_total'] ?? 100;

        $checkout = $this->korapayService->initializePayment($amount);
        dd($checkout);

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

    private function getTotal($item)
    {
        if ($item instanceof Order) {
            return $item['grand_total'] ?? 100;
        } elseif ($item instanceof Cart) {

            dd($item->getSummery());
        }
    }
}
