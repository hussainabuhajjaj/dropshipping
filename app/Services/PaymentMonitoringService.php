<?php

namespace App\Services;

use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentWebhook;
use App\Domain\Payments\Models\PaymentEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Mail\PaymentMonitoringAlert;

class PaymentMonitoringService
{
    /**
     * Monitor for failed webhooks and send alerts
     */
    public function monitorFailedWebhooks(): array
    {
        $failedWebhooks = $this->getFailedWebhooks();
        $alerts = [];

        foreach ($failedWebhooks as $webhook) {
            $alert = $this->createWebhookFailureAlert($webhook);
            $alerts[] = $alert;
            
            // Send email alert
            $this->sendAlertEmail($alert);
            
            // Log for monitoring
            Log::warning('Payment webhook failure detected', [
                'webhook_id' => $webhook->id,
                'payment_id' => $webhook->payment_id,
                'provider' => $webhook->provider,
                'alert_type' => 'webhook_failure',
            ]);
        }

        return $alerts;
    }

    /**
     * Monitor redirect rates (customers who don't return after payment)
     */
    public function monitorRedirectRates(): array
    {
        $redirectIssues = $this->getRedirectIssues();
        $alerts = [];

        foreach ($redirectIssues as $issue) {
            $alert = $this->createRedirectRateAlert($issue);
            $alerts[] = $alert;
            
            // Send email alert
            $this->sendAlertEmail($alert);
            
            Log::warning('Payment redirect issue detected', [
                'payment_id' => $issue['payment_id'],
                'order_number' => $issue['order_number'],
                'issue_type' => 'redirect_not_completed',
                'minutes_since_init' => $issue['minutes_since_init'],
            ]);
        }

        return $alerts;
    }

    /**
     * Track payment completion times and anomalies
     */
    public function trackPaymentCompletionTimes(): array
    {
        $completionStats = $this->getCompletionStats();
        $alerts = [];

        // Check for unusually long completion times
        if ($completionStats['avg_completion_minutes'] > 30) {
            $alert = [
                'type' => 'slow_completion',
                'severity' => 'warning',
                'message' => "Average payment completion time is {$completionStats['avg_completion_minutes']} minutes",
                'data' => $completionStats,
                'created_at' => now(),
            ];
            
            $alerts[] = $alert;
            $this->sendAlertEmail($alert);
            
            Log::warning('Slow payment completion detected', $completionStats);
        }

        return $alerts;
    }

    /**
     * Audit data capture rates regularly
     */
    public function auditDataCaptureRates(): array
    {
        $auditResults = $this->getDataCaptureAudit();
        $alerts = [];

        foreach ($auditResults as $result) {
            if ($result['capture_rate'] < 80) {
                $alert = [
                    'type' => 'poor_data_capture',
                    'severity' => 'critical',
                    'message' => "Data capture rate is only {$result['capture_rate']}% for order {$result['order_number']}",
                    'data' => $result,
                    'created_at' => now(),
                ];
                
                $alerts[] = $alert;
                $this->sendAlertEmail($alert);
                
                Log::error('Poor data capture rate detected', $result);
            }
        }

        return $alerts;
    }

    /**
     * Get webhooks that failed to process
     */
    private function getFailedWebhooks(): array
    {
        return PaymentWebhook::whereNull('processed_at')
            ->where('created_at', '<', now()->subHours(1))
            ->with('payment')
            ->get()
            ->toArray();
    }

    /**
     * Get payments with redirect issues
     */
    private function getRedirectIssues(): array
    {
        return Payment::where('provider', 'korapay')
            ->where('status', 'pending')
            ->where('created_at', '>', now()->subMinutes(30))
            ->whereDoesntHave('webhooks') // No webhook received
            ->where(function ($query) {
                $query->whereNull('meta->redirect_hit_at')
                      ->orWhere('meta->redirect_hit_at', '<', now()->subMinutes(15));
            })
            ->with('order')
            ->get(['id', 'provider_reference', 'meta', 'created_at'])
            ->map(function ($payment) {
                $initTime = $payment->created_at;
                $redirectTime = isset($payment->meta['redirect_hit_at']) 
                    ? \Carbon\Carbon::parse($payment->meta['redirect_hit_at']) 
                    : null;

                return [
                    'payment_id' => $payment->id,
                    'order_number' => $payment->order->number ?? 'N/A',
                    'provider_reference' => $payment->provider_reference,
                    'minutes_since_init' => $redirectTime ? $initTime->diffInMinutes($redirectTime) : 'N/A',
                    'init_time' => $initTime,
                    'redirect_time' => $redirectTime,
                    'issue' => $redirectTime ? 'partial_redirect' : 'no_redirect',
                ];
            })
            ->toArray();
    }

    /**
     * Get payment completion statistics
     */
    private function getCompletionStats(): array
    {
        $completedPayments = Payment::where('status', 'paid')
            ->where('provider', 'korapay')
            ->where('created_at', '>', now()->subDays(7))
            ->get(['id', 'created_at', 'paid_at']);

        if ($completedPayments->isEmpty()) {
            return [
                'total_completed' => 0,
                'avg_completion_minutes' => 0,
                'min_completion_minutes' => 0,
                'max_completion_minutes' => 0,
            ];
        }

        $completionTimes = $completedPayments->map(function ($payment) {
            return $payment->created_at->diffInMinutes($payment->paid_at);
        })->filter();

        return [
            'total_completed' => $completedPayments->count(),
            'avg_completion_minutes' => round($completionTimes->avg(), 1),
            'min_completion_minutes' => $completionTimes->min(),
            'max_completion_minutes' => $completionTimes->max(),
        ];
    }

    /**
     * Get data capture audit results
     */
    private function getDataCaptureAudit(): array
    {
        $recentPayments = Payment::where('provider', 'korapay')
            ->where('created_at', '>', now()->subHours(24))
            ->with(['webhooks', 'events'])
            ->get();

        $auditResults = [];

        foreach ($recentPayments as $payment) {
            $dataPoints = [
                'request' => isset($payment->meta['request']),
                'korapay_init' => isset($payment->meta['korapay_init']),
                'redirect_hit_at' => isset($payment->meta['redirect_hit_at']),
                'redirect_payload' => isset($payment->meta['redirect_payload']),
                'webhook_received' => $payment->webhooks->count() > 0,
                'events_recorded' => $payment->events->count() > 0,
            ];

            $capturedCount = count(array_filter($dataPoints));
            $totalCount = count($dataPoints);
            $captureRate = round(($capturedCount / $totalCount) * 100, 1);

            $auditResults[] = [
                'payment_id' => $payment->id,
                'order_number' => $payment->order->number ?? 'N/A',
                'provider_reference' => $payment->provider_reference,
                'capture_rate' => $captureRate,
                'data_points' => $dataPoints,
                'missing_points' => array_keys(array_diff_assoc($dataPoints, array_filter($dataPoints))),
            ];
        }

        return $auditResults;
    }

    /**
     * Create webhook failure alert
     */
    private function createWebhookFailureAlert(array $webhook): array
    {
        return [
            'type' => 'webhook_failure',
            'severity' => 'critical',
            'message' => "Webhook {$webhook->external_event_id} failed to process for payment {$webhook->payment_id}",
            'data' => [
                'webhook_id' => $webhook->id,
                'payment_id' => $webhook->payment_id,
                'provider' => $webhook->provider,
                'external_event_id' => $webhook->external_event_id,
                'created_at' => $webhook->created_at,
            ],
            'created_at' => now(),
        ];
    }

    /**
     * Create redirect rate alert
     */
    private function createRedirectRateAlert(array $issue): array
    {
        $message = $issue['issue'] === 'no_redirect' 
            ? "Customer never redirected for order {$issue['order_number']} ({$issue['minutes_since_init']} minutes)"
            : "Customer redirected but didn't complete for order {$issue['order_number']}";

        return [
            'type' => 'redirect_issue',
            'severity' => $issue['minutes_since_init'] > 60 ? 'critical' : 'warning',
            'message' => $message,
            'data' => $issue,
            'created_at' => now(),
        ];
    }

    /**
     * Send alert email
     */
    private function sendAlertEmail(array $alert): void
    {
        try {
            $recipients = config('payment.monitoring.alerts_email', ['admin@example.com']);
            
            foreach ($recipients as $recipient) {
                Mail::to($recipient)->send(new PaymentMonitoringAlert($alert));
            }
            
            Log::info('Payment monitoring alert sent', [
                'alert_type' => $alert['type'],
                'severity' => $alert['severity'],
                'recipients' => $recipients,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send payment monitoring alert', [
                'alert' => $alert,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
