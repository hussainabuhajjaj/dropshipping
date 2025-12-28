<?php

declare(strict_types=1);

namespace App\Domain\Orders\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Domain\Orders\Models\OrderAuditLog;
use Illuminate\Support\Facades\DB;

class LinkGuestOrdersService
{
    /**
     * Link all guest orders matching the given email to the customer.
     *
     * @return int Number of orders linked
     */
    public function linkByEmail(string $email, int $customerId, ?string $phoneLast4 = null): int
    {
        $email = trim(strtolower($email));
        if ($email === '') {
            return 0;
        }

        $customer = Customer::query()->find($customerId);
        if (! $customer) {
            return 0;
        }

        $orders = Order::query()
            ->whereNull('customer_id')
            ->where('is_guest', true)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->when($phoneLast4 !== null && $phoneLast4 !== '', function ($query) use ($phoneLast4) {
                $query->whereRaw('RIGHT(guest_phone, 4) = ?', [preg_replace('/\D+/', '', $phoneLast4)]);
            })
            ->get();

        if ($orders->isEmpty()) {
            return 0;
        }

        $linked = 0;

        DB::transaction(function () use (&$linked, $orders, $customer): void {
            foreach ($orders as $order) {
                $order->customer_id = $customer->id;
                $order->is_guest = false;
                // Preserve guest_name/guest_phone as historical fields; update if empty
                if (empty($order->guest_name)) {
                    $order->guest_name = $customer->name;
                }
                if (empty($order->guest_phone) && ! empty($customer->phone)) {
                    $order->guest_phone = $customer->phone;
                }
                $order->save();

                OrderAuditLog::create([
                    'order_id' => $order->id,
                    'user_id' => null,
                    'action' => 'link_to_customer',
                    'note' => 'Order linked to customer upon registration',
                    'payload' => [
                        'customer_id' => $customer->id,
                        'customer_email' => $customer->email,
                    ],
                ]);

                $linked++;
            }
        });

        return $linked;
    }
}
