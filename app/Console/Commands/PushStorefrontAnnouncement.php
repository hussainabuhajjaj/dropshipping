<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Events\Storefront\StorefrontAnnouncementPushed;
use Illuminate\Console\Command;

class PushStorefrontAnnouncement extends Command
{
    protected $signature = 'storefront:announce
        {message : The message to broadcast}
        {--level=warning : warning|info|success|danger}
        {--id= : Optional announcement id (helps rotate dismissal)}
        {--dismissible=1 : 1/0}
        {--dry-run : Do not broadcast, just print payload}';

    protected $description = 'Broadcast a real-time announcement to all active storefront browser sessions';

    public function handle(): int
    {
        $driver = (string) config('broadcasting.default', 'log');
        if ($driver !== 'pusher') {
            $this->warn("Broadcast driver is '{$driver}'. Set BROADCAST_CONNECTION=pusher on production for realtime.");
        }

        $message = trim((string) $this->argument('message'));
        if ($message === '') {
            $this->error('Message cannot be empty.');
            return self::FAILURE;
        }

        $level = strtolower(trim((string) $this->option('level')));
        if (! in_array($level, ['warning', 'info', 'success', 'danger'], true)) {
            $this->error('Invalid --level. Use warning|info|success|danger.');
            return self::FAILURE;
        }

        $id = $this->option('id');
        $id = is_string($id) && trim($id) !== '' ? trim($id) : null;

        $dismissible = (string) $this->option('dismissible') !== '0';
        $dryRun = (bool) $this->option('dry-run');

        $payload = [
            'message' => $message,
            'level' => $level,
            'dismissible' => $dismissible,
            'id' => $id,
        ];

        $this->line('Storefront announcement: ' . json_encode($payload, JSON_UNESCAPED_SLASHES));

        if ($dryRun) {
            $this->info('DRY-RUN: not broadcasting.');
            return self::SUCCESS;
        }

        event(new StorefrontAnnouncementPushed(
            message: $message,
            level: $level,
            dismissible: $dismissible,
            id: $id,
        ));

        $this->info('Broadcasted.');
        return self::SUCCESS;
    }
}

