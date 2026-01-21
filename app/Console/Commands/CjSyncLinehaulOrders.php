<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Models\Order;
use App\Domain\Orders\Models\LinehaulShipment;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CjSyncLinehaulOrders extends Command
{
    protected $signature = 'cj:sync-linehaul-orders
        {--page=1 : Page number}
        {--page-size=20 : Results per page}
        {--status= : CJ order status filter (CREATED,IN_CART,UNPAID,UNSHIPPED,SHIPPED,DELIVERED,CANCELLED,OTHER)}
        {--order-id=* : Specific CJ order IDs}
        {--shipment-order-id= : CJ shipment order id}
        {--dry-run : Do not persist updates}' ;

    protected $description = 'Fetch CJ order list and attach snapshots to matching linehaul shipments.';

    public function handle(CJDropshippingClient $client): int
    {
        $payload = [
            'pageNum' => (int) $this->option('page'),
            'pageSize' => (int) $this->option('page-size'),
        ];

        if ($this->option('status')) {
            $payload['status'] = (string) $this->option('status');
        }

        $orderIds = $this->option('order-id');
        if (is_array($orderIds) && count($orderIds)) {
            $payload['orderIds'] = array_values(array_filter($orderIds));
        }

        if ($this->option('shipment-order-id')) {
            $payload['shipmentOrderId'] = (string) $this->option('shipment-order-id');
        }

        $response = $client->getOrderList($payload);
        $data = is_array($response->data) ? $response->data : [];
        $list = $data['list'] ?? [];

        if (! is_array($list) || empty($list)) {
            $this->info('No CJ orders returned.');
            return self::SUCCESS;
        }

        $orderIdList = collect($list)
            ->pluck('orderId')
            ->filter()
            ->unique()
            ->values();

        $orders = Order::query()
            ->with('linehaulShipment')
            ->whereIn('cj_order_id', $orderIdList->all())
            ->get()
            ->keyBy('cj_order_id');

        $updated = 0;
        $skipped = 0;
        $dryRun = (bool) $this->option('dry-run');

        foreach ($list as $cjOrder) {
            if (! is_array($cjOrder)) {
                $skipped++;
                continue;
            }

            $cjOrderId = $cjOrder['orderId'] ?? null;
            if (! $cjOrderId) {
                $skipped++;
                continue;
            }

            $order = $orders->get($cjOrderId);
            if (! $order || ! $order->linehaulShipment) {
                $skipped++;
                continue;
            }

            /** @var LinehaulShipment $shipment */
            $shipment = $order->linehaulShipment;
            $shipment->applyCjOrder($cjOrder);

            if (! $dryRun) {
                $shipment->save();
            }

            $updated++;
        }

        $this->info(sprintf('CJ orders fetched: %d | Updated linehaul shipments: %d | Skipped: %d', count($list), $updated, $skipped));

        if ($dryRun) {
            Log::info('CJ linehaul sync dry-run completed', [
                'updated' => $updated,
                'skipped' => $skipped,
                'page' => $payload['pageNum'],
            ]);
        }

        return self::SUCCESS;
    }
}
