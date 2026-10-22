<?php

declare(strict_types=1);

namespace App\Listeners\Campaigns;

use App\Domain\Campaigns\Models\CampaignParticipation;
use App\Domain\Campaigns\Services\LuckyDrawService;
use App\Events\Orders\OrderPaid;
use App\Notifications\Campaigns\LuckyDrawQualifiedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class RegisterCampaignParticipation implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'campaigns';

    public int $tries = 3;

    public function __construct(
        private readonly LuckyDrawService $service,
    ) {
    }

    public function handle(OrderPaid $event): void
    {
        $order = $event->order;

        try {
            $participations = $this->service->registerQualifiedOrder($order);

            foreach ($participations as $participation) {
                $this->notifyCustomer($participation);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to register campaign participation', [
                'order_id' => $order->id,
                'order_number' => $order->number,
                'exception' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function notifyCustomer(CampaignParticipation $participation): void
    {
        if (! $participation->customer) {
            return;
        }

        try {
            $participation->customer->notify(new LuckyDrawQualifiedNotification(
                $participation->campaign,
                $participation,
            ));
        } catch (\Throwable $e) {
            Log::warning('Failed to send lucky draw qualification notification', [
                'participation_id' => $participation->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
