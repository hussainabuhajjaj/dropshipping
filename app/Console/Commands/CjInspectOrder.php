<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Fulfillment\Models\FulfillmentJob;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Services\Api\ApiException;
use Illuminate\Console\Command;

class CjInspectOrder extends Command
{
    protected $signature = 'cj:inspect-order
                            {order_number : CJ order number (e.g. SD2604091153000652400)}
                            {--pretty : Pretty-print JSON output}
                            {--local : Also search local fulfillment_jobs for this reference}';

    protected $description = 'Fetch CJ order detail/status for an order number and optionally find matching local fulfillment jobs.';

    public function handle(): int
    {
        $orderNumber = trim((string) $this->argument('order_number'));
        if ($orderNumber === '') {
            $this->error('order_number is required');
            return self::INVALID;
        }

        $pretty = (bool) $this->option('pretty');
        $searchLocal = (bool) $this->option('local');

        $client = app(CJDropshippingClient::class);

        $this->info("CJ inspect: {$orderNumber}");

        $detailResp = null;
        $listResp = null;

        try {
            // CJ docs: /v1/shopping/order/getOrderDetail?orderId=... (supports custom order id like SDxxxx)
            $detailResp = $client->getOrderDetail(['orderId' => $orderNumber]);
        } catch (ApiException $e) {
            $this->warn('Order Detail (orderId) failed: ' . $e->getMessage());
            $this->dumpThrowable($e, $pretty);
        }

        if (! $detailResp || empty($detailResp->data)) {
            try {
                $listResp = $client->getOrderList([
                    'pageNum' => 1,
                    'pageSize' => 5,
                    'orderNumber' => $orderNumber,
                ]);
            } catch (ApiException $e) {
                $this->warn('Order List lookup failed: ' . $e->getMessage());
                $this->dumpThrowable($e, $pretty);
            }
        }

        $this->line('');
        $this->line('** Order Detail (orderId) **');
        if ($detailResp) {
            $this->dumpResponse($detailResp, $pretty);
        } else {
            $this->line('(not available)');
        }

        if ($listResp) {
            $this->line('');
            $this->line('** Order List (orderNumber) **');
            $this->dumpResponse($listResp, $pretty);
        }

        if ($searchLocal) {
            $this->line('');
            $this->line('** Local Fulfillment Jobs (match) **');

            $jobs = FulfillmentJob::query()
                ->where('external_reference', $orderNumber)
                ->orWhere('payload', 'like', '%' . $orderNumber . '%')
                ->orderByDesc('id')
                ->limit(10)
                ->get(['id', 'order_id', 'order_item_id', 'status', 'external_reference', 'dispatched_at', 'fulfilled_at', 'last_error']);

            if ($jobs->isEmpty()) {
                $this->warn('No matching fulfillment_jobs found.');
            } else {
                $this->table(
                    ['id', 'order_id', 'order_item_id', 'status', 'external_reference', 'dispatched_at', 'fulfilled_at', 'last_error'],
                    $jobs->map(fn ($j) => [
                        $j->id,
                        $j->order_id,
                        $j->order_item_id,
                        $j->status,
                        $j->external_reference,
                        optional($j->dispatched_at)->toDateTimeString(),
                        optional($j->fulfilled_at)->toDateTimeString(),
                        $j->last_error ? mb_strimwidth((string) $j->last_error, 0, 120, '...') : null,
                    ])->all()
                );
            }
        }

        return self::SUCCESS;
    }

    private function dumpResponse($resp, bool $pretty): void
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $payload = [
            'ok' => $resp->ok ?? null,
            'status' => $resp->status ?? null,
            'message' => $resp->message ?? null,
            'data' => $resp->data ?? null,
        ];

        $json = json_encode($payload, $flags);
        $this->line($json !== false ? $json : print_r($payload, true));
    }

    private function dumpThrowable(ApiException $e, bool $pretty): void
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $payload = [
            'status' => $e->status,
            'code' => $e->codeString,
            'message' => $e->getMessage(),
            'body' => $e->body,
        ];

        $json = json_encode($payload, $flags);
        $this->line($json !== false ? $json : print_r($payload, true));
    }
}
