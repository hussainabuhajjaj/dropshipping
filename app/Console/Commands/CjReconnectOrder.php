<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Common\Models\Address;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Products\Models\ProductVariant;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CjReconnectOrder extends Command
{
    protected $signature = 'cj:reconnect-order
                            {cj_order_code : CJ order code (e.g. SD2604091153000652400)}
                            {customer : Customer id or email to attach this order to}
                            {--create-missing : If the local order is missing, recreate a minimal order + items}
                            {--sync-payment : Also set local payment fields based on CJ paymentDate}
                            {--sync-items : If local order_items are missing, recreate from CJ productList}
                            {--dry-run : Show what would happen without writing to DB}';

    protected $description = 'Fetch a CJ order snapshot and reconnect the corresponding local order (orderNum) to a customer.';

    public function handle(): int
    {
        $cjOrderCode = trim((string) $this->argument('cj_order_code'));
        $customerArg = trim((string) $this->argument('customer'));

        $createMissing = (bool) $this->option('create-missing');
        $syncPayment = (bool) $this->option('sync-payment');
        $syncItems = (bool) $this->option('sync-items');
        $dryRun = (bool) $this->option('dry-run');

        if ($cjOrderCode === '' || $customerArg === '') {
            $this->error('cj_order_code and customer are required.');
            return self::INVALID;
        }

        $customer = $this->resolveCustomer($customerArg);
        if (! $customer) {
            $this->error("Customer not found for: {$customerArg}");
            return self::FAILURE;
        }

        $client = app(CJDropshippingClient::class);
        $this->info("CJ reconnect: {$cjOrderCode} -> customer {$customer->id} ({$customer->email})");

        $resp = $client->getOrderDetail(['orderId' => $cjOrderCode]);
        $cj = is_array($resp->data) ? $resp->data : [];

        $localOrderNumber = trim((string) ($cj['orderNum'] ?? $cj['orderNumber'] ?? ''));
        if ($localOrderNumber === '') {
            $this->error('CJ order detail did not include orderNum (local order number).');
            $this->line(json_encode($cj, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return self::FAILURE;
        }

        $order = Order::query()->where('number', $localOrderNumber)->first();

        if (! $order && ! $createMissing) {
            $this->error("Local order not found: {$localOrderNumber}. Re-run with --create-missing to recreate.");
            return self::FAILURE;
        }

        if (! $order) {
            $this->warn("Local order missing, will recreate: {$localOrderNumber}");
            if ($dryRun) {
                $this->line('[DRY RUN] Would create Order + Address + OrderItems (optional).');
                return self::SUCCESS;
            }

            $address = Address::create([
                'customer_id' => $customer->id,
                'name' => (string) ($cj['shippingCustomerName'] ?? $customer->name),
                'phone' => (string) ($cj['shippingPhone'] ?? $customer->phone),
                'line1' => (string) ($cj['shippingAddress'] ?? ''),
                'line2' => null,
                'city' => (string) ($cj['shippingCity'] ?? ''),
                'state' => (string) ($cj['shippingProvince'] ?? ''),
                'postal_code' => (string) ($cj['shippingZip'] ?? ''),
                'country' => (string) ($cj['shippingCountryCode'] ?? ($customer->country_code ?? '')),
                'type' => 'shipping',
                'metadata' => [
                    'source' => 'cj:reconnect-order',
                    'cj_order_code' => $cjOrderCode,
                ],
            ]);

            $order = Order::create([
                'number' => $localOrderNumber,
                'customer_id' => $customer->id,
                'email' => (string) $customer->email,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'currency' => 'USD',
                'subtotal' => (float) ($cj['productAmount'] ?? 0),
                'shipping_total' => (float) ($cj['postageAmount'] ?? 0),
                'tax_total' => 0,
                'discount_total' => 0,
                'grand_total' => (float) ($cj['orderAmount'] ?? 0),
                'shipping_address_id' => $address->id,
                'billing_address_id' => null,
                'placed_at' => $this->parseCjDatetime($cj['createDate'] ?? null),
                'delivery_notes' => null,
                'shipping_method' => (string) ($cj['logisticName'] ?? null),
                'cost_breakdown' => [
                    'cj_order_code' => $cjOrderCode,
                    'cj_snapshot' => $cj,
                ],
            ]);
        }

        $this->line("Local order: #{$order->id} {$order->number}");

        $updates = [
            'customer_id' => $customer->id,
            'email' => (string) $customer->email,
            'cj_order_id' => (string) ($cj['orderId'] ?? $order->cj_order_id),
            'cj_order_status' => (string) ($cj['orderStatus'] ?? $order->cj_order_status),
            'cj_order_created_at' => $this->parseCjDatetime($cj['createDate'] ?? null) ?? $order->cj_order_created_at,
            // Keep a stable reference to the CJ SD... code for future reconciliation.
            'cost_breakdown' => array_merge($order->cost_breakdown ?? [], [
                'cj_order_code' => $cjOrderCode,
            ]),
        ];

        if ($syncPayment) {
            $paidAt = $this->parseCjDatetime($cj['paymentDate'] ?? null);
            $updates = array_merge($updates, [
                'cj_payment_status' => $paidAt ? 'paid' : ($order->cj_payment_status ?? 'pending'),
                'cj_paid_at' => $paidAt,
            ]);
        }

        if ($dryRun) {
            $this->line('[DRY RUN] Would update: ' . json_encode($updates, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $order->forceFill($updates)->save();
        }

        if ($syncItems) {
            $this->syncOrderItemsFromCj($order, $cj, $dryRun);
        }

        $this->info('Done.');
        return self::SUCCESS;
    }

    private function resolveCustomer(string $arg): ?Customer
    {
        if (ctype_digit($arg)) {
            return Customer::query()->whereKey((int) $arg)->first();
        }

        $email = Str::lower(trim($arg));
        return Customer::query()->whereRaw('LOWER(email) = ?', [$email])->first();
    }

    private function parseCjDatetime(?string $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function syncOrderItemsFromCj(Order $order, array $cj, bool $dryRun): void
    {
        $existingCount = $order->orderItems()->count();
        if ($existingCount > 0) {
            $this->line("Order already has {$existingCount} item(s); skipping item recreation.");
            return;
        }

        $list = $cj['productList'] ?? null;
        if (! is_array($list) || $list === []) {
            $this->warn('CJ order detail did not include productList; cannot recreate items.');
            return;
        }

        $rows = [];
        foreach ($list as $row) {
            if (! is_array($row)) {
                continue;
            }

            $vid = trim((string) ($row['vid'] ?? ''));
            $qty = (int) ($row['quantity'] ?? 0);
            $unit = (float) ($row['sellPrice'] ?? 0);

            if ($vid === '' || $qty <= 0) {
                continue;
            }

            $variantId = null;
            $variant = ProductVariant::query()->where('cj_vid', $vid)->first();
            if ($variant) {
                $variantId = $variant->id;
            }

            $rows[] = [
                'order_id' => $order->id,
                'product_variant_id' => $variantId,
                'fulfillment_provider_id' => null,
                'supplier_product_id' => null,
                'fulfillment_status' => 'pending',
                'quantity' => $qty,
                'unit_price' => $unit,
                'total' => round($unit * $qty, 2),
                'source_sku' => null,
                'snapshot' => [
                    'cj' => $row,
                ],
                'meta' => [
                    'cj_vid' => $vid,
                    'cj_order_code' => $cj['cjOrderCode'] ?? null,
                    'cj_line_item_id' => $row['lineItemId'] ?? null,
                    'store_line_item_id' => $row['storeLineItemId'] ?? null,
                ],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows === []) {
            $this->warn('No valid productList rows found to recreate items.');
            return;
        }

        if ($dryRun) {
            $this->line('[DRY RUN] Would create ' . count($rows) . ' order_items.');
            return;
        }

        OrderItem::query()->insert($rows);
        $this->line('Created ' . count($rows) . ' order_items.');
    }
}

