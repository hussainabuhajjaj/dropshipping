<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

class WhatsAppChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $phone = $notifiable->phone ?? null;
        if (! $phone) {
            return;
        }

        $message = trim((string) $notification->toWhatsApp($notifiable));
        if ($message === '') {
            return;
        }

        $config = config('services.whatsapp', []);
        if (! is_array($config)) {
            return;
        }

        $provider = $config['provider'] ?? null;
        if (! $provider) {
            return;
        }

        $timeout = (int) ($config['timeout'] ?? 10);

        switch ($provider) {
            case 'meta':
                $meta = $config['meta'] ?? [];
                $token = $meta['token'] ?? null;
                $phoneNumberId = $meta['phone_number_id'] ?? null;
                if (! $token || ! $phoneNumberId) {
                    return;
                }

                $baseUrl = rtrim($meta['base_url'] ?? 'https://graph.facebook.com', '/');
                $apiVersion = $meta['api_version'] ?? 'v19.0';

                Http::withToken($token)
                    ->timeout($timeout)
                    ->post("{$baseUrl}/{$apiVersion}/{$phoneNumberId}/messages", [
                        'messaging_product' => 'whatsapp',
                        'to' => $phone,
                        'type' => 'text',
                        'text' => ['body' => $message],
                    ]);
                return;
            case 'twilio':
                $twilio = $config['twilio'] ?? [];
                $sid = $twilio['sid'] ?? null;
                $token = $twilio['token'] ?? null;
                $from = $twilio['from'] ?? null;
                if (! $sid || ! $token || ! $from) {
                    return;
                }

                $to = str_starts_with($phone, 'whatsapp:') ? $phone : "whatsapp:{$phone}";
                $from = str_starts_with($from, 'whatsapp:') ? $from : "whatsapp:{$from}";

                Http::withBasicAuth($sid, $token)
                    ->timeout($timeout)
                    ->asForm()
                    ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                        'To' => $to,
                        'From' => $from,
                        'Body' => $message,
                    ]);
                return;
            case 'vonage':
                $vonage = $config['vonage'] ?? [];
                $jwt = $vonage['jwt'] ?? null;
                $from = $vonage['from'] ?? null;
                $endpoint = $vonage['endpoint'] ?? 'https://api.nexmo.com/v1/messages';
                if (! $jwt || ! $from) {
                    return;
                }

                Http::withToken($jwt)
                    ->timeout($timeout)
                    ->post($endpoint, [
                        'from' => $from,
                        'to' => $phone,
                        'channel' => 'whatsapp',
                        'message_type' => 'text',
                        'text' => $message,
                    ]);
                return;
            default:
                return;
        }
    }
}
