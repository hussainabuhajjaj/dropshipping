<?php

declare(strict_types=1);

namespace App\Infrastructure\Fulfillment\Clients\CJ;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\CjAlert;

class CjAlertService
{
    public static function alert(string $title, array $context = []): void
    {
        // Always log for now
        Log::error("CJ Alert: {$title}", $context);

        // If Sentry service is bound in the container, call its capture method
        if (function_exists('app') && app()->bound('sentry')) {
            try {
                $sentry = app('sentry');
                if (is_callable([$sentry, 'captureMessage'])) {
                    $sentry->captureMessage("CJ Alert: {$title}");
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to send to Sentry', ['error' => $e->getMessage()]);
            }
        }

        // Send an email if configured
        $email = config('services.cj.alerts_email');
        if ($email) {
            try {
                Mail::to($email)->send(new CjAlert($title, $context));
            } catch (\Throwable $e) {
                Log::warning('Failed to send CJ alert email', ['error' => $e->getMessage()]);
            }
        }
    }
}
