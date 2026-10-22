<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Jobs;

use App\Domain\WooCommerce\Services\WooCommerceWebhookHandlerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWooWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 3;

    public function __construct(
        private readonly string $eventType,
        private readonly array $payload,
        private readonly string $rawPayload,
        private readonly string $signature,
        private readonly string $deliveryId = '',
        private readonly string $resource = '',
        private readonly string $event = '',
    ) {
        $this->onQueue(config('woocommerce.queue', 'woocommerce'));
    }

    public function handle(WooCommerceWebhookHandlerService $handler): void
    {
        if (! config('woocommerce.enabled', false)) {
            Log::info('WooCommerce webhook processing skipped: integration disabled');

            return;
        }

        $handler->handle(
            $this->eventType,
            $this->payload,
            $this->rawPayload,
            $this->signature,
            $this->deliveryId,
            $this->resource,
            $this->event,
        );
    }

    public function failed(\Throwable $e): void
    {
        Log::error('WooCommerce webhook job failed permanently', [
            'event_type' => $this->eventType,
            'error' => $e->getMessage(),
        ]);
    }
}
