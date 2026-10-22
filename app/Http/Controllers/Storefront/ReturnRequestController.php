<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReturnRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');
        if (! $customer) {
            abort(403);
        }

        $data = $request->validate([
            'order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'reason' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $orderItem = OrderItem::query()
            ->with(['order', 'returnRequest'])
            ->where('id', $data['order_item_id'])
            ->where('fulfillment_status', 'fulfilled')
            ->whereHas('order', function ($builder) use ($customer) {
                $builder
                    ->where('customer_id', $customer->id)
                    ->where('status', 'fulfilled');
            })
            ->firstOrFail();

        if ($orderItem->returnRequest) {
            return back()->withErrors([
                'order_item_id' => 'Return already requested for this item.',
            ]);
        }

        ReturnRequest::create([
            'order_id' => $orderItem->order_id,
            'order_item_id' => $orderItem->id,
            'customer_id' => $customer->id,
            'status' => 'requested',
            'reason' => $data['reason'],
            'notes' => $data['notes'],
        ]);

        return back()->with('return_notice', 'Return request submitted.');
    }
}
