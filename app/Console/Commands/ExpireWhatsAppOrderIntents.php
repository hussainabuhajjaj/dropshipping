<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Checkout\WhatsAppOrderIntentService;
use Illuminate\Console\Command;

class ExpireWhatsAppOrderIntents extends Command
{
    protected $signature = 'whatsapp-intents:expire';

    protected $description = 'Mark stale WhatsApp order intents as expired';

    public function handle(WhatsAppOrderIntentService $service): int
    {
        $count = $service->expireStaleIntents();

        $this->info("Expired {$count} WhatsApp intent(s).");

        return self::SUCCESS;
    }
}
