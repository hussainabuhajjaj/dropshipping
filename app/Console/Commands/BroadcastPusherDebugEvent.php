<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Events\PusherDebugEvent;
use Illuminate\Console\Command;

class BroadcastPusherDebugEvent extends Command
{
    protected $signature = 'broadcast:pusher-test
        {--channel=my-channel : Channel name to broadcast to}
        {--event=my-event : Event name (without leading dot)}
        {--message=hello world : Simple message payload}
        {--json= : Full JSON payload override (object)}';

    protected $description = 'Broadcast a debug event to Pusher / Laravel Echo channels';

    public function handle(): int
    {
        $channel = trim((string) $this->option('channel'));
        $event = ltrim(trim((string) $this->option('event')), '.');
        $message = (string) $this->option('message');
        $json = $this->option('json');

        if ($channel === '' || $event === '') {
            $this->error('Both --channel and --event are required.');

            return self::FAILURE;
        }

        $payload = ['message' => $message];

        if (is_string($json) && trim($json) !== '') {
            $decoded = json_decode($json, true);
            if (! is_array($decoded)) {
                $this->error('Invalid --json payload. Provide a valid JSON object.');

                return self::FAILURE;
            }

            $payload = $decoded;
        }

        event(new PusherDebugEvent($channel, $event, $payload));

        $this->info('Broadcast dispatched.');
        $this->line("Channel: {$channel}");
        $this->line("Event: .{$event}");
        $this->line('Payload: ' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}

