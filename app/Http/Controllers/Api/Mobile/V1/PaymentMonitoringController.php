<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Api\Mobile\V1\ApiController;
use App\Services\PaymentMonitoringService;
use Illuminate\Http\JsonResponse;

class PaymentMonitoringController extends ApiController
{
    /**
     * Get payment monitoring dashboard
     */
    public function dashboard(PaymentMonitoringService $monitoringService): JsonResponse
    {
        $data = [
            'failed_webhooks' => $monitoringService->monitorFailedWebhooks(),
            'redirect_issues' => $monitoringService->monitorRedirectRates(),
            'completion_stats' => $monitoringService->trackPaymentCompletionTimes(),
            'capture_audit' => $monitoringService->auditDataCaptureRates(),
        ];

        return $this->success($data, 'Payment monitoring data retrieved');
    }

    /**
     * Run health checks manually
     */
    public function healthCheck(PaymentMonitoringService $monitoringService): JsonResponse
    {
        $allAlerts = [
            'webhook_failures' => $monitoringService->monitorFailedWebhooks(),
            'redirect_issues' => $monitoringService->monitorRedirectRates(),
            'completion_issues' => $monitoringService->trackPaymentCompletionTimes(),
            'capture_issues' => $monitoringService->auditDataCaptureRates(),
        ];

        $totalIssues = array_sum(array_map('count', $allAlerts));
        $status = $totalIssues === 0 ? 'healthy' : 'issues_detected';

        return $this->success([
            'status' => $status,
            'total_issues' => $totalIssues,
            'alerts' => array_merge(...array_values($allAlerts)),
            'checked_at' => now(),
        ], 'Payment health check completed');
    }

    /**
     * Get monitoring statistics
     */
    public function statistics(PaymentMonitoringService $monitoringService): JsonResponse
    {
        $stats = [
            'webhooks_processed_today' => $this->getWebhooksProcessedToday(),
            'redirects_completed_today' => $this->getRedirectsCompletedToday(),
            'payments_completed_today' => $this->getPaymentsCompletedToday(),
            'average_completion_time' => $this->getAverageCompletionTime(),
            'data_capture_rate' => $this->getDataCaptureRate(),
        ];

        return $this->success($stats, 'Payment monitoring statistics retrieved');
    }

    private function getWebhooksProcessedToday(): int
    {
        return \App\Domain\Payments\Models\PaymentWebhook::whereDate('created_at', today())
            ->whereNotNull('processed_at')
            ->count();
    }

    private function getRedirectsCompletedToday(): int
    {
        return \App\Domain\Payments\Models\Payment::where('provider', 'korapay')
            ->whereNotNull('meta->redirect_hit_at')
            ->whereDate('meta->redirect_hit_at', today())
            ->count();
    }

    private function getPaymentsCompletedToday(): int
    {
        return \App\Domain\Payments\Models\Payment::where('status', 'paid')
            ->whereDate('paid_at', today())
            ->count();
    }

    private function getAverageCompletionTime(): float
    {
        $completedPayments = \App\Domain\Payments\Models\Payment::where('status', 'paid')
            ->whereNotNull('paid_at')
            ->whereNotNull('created_at')
            ->get(['created_at', 'paid_at']);

        if ($completedPayments->isEmpty()) {
            return 0.0;
        }

        $totalMinutes = $completedPayments->sum(function ($payment) {
            return $payment->created_at->diffInMinutes($payment->paid_at);
        });

        return round($totalMinutes / $completedPayments->count(), 1);
    }

    private function getDataCaptureRate(): float
    {
        $recentPayments = \App\Domain\Payments\Models\Payment::where('provider', 'korapay')
            ->where('created_at', '>', now()->subHours(24))
            ->with(['webhooks', 'events'])
            ->get();

        if ($recentPayments->isEmpty()) {
            return 100.0;
        }

        $totalDataPoints = 0;
        $capturedDataPoints = 0;

        foreach ($recentPayments as $payment) {
            $dataPoints = [
                'request' => isset($payment->meta['request']),
                'korapay_init' => isset($payment->meta['korapay_init']),
                'redirect_hit_at' => isset($payment->meta['redirect_hit_at']),
                'redirect_payload' => isset($payment->meta['redirect_payload']),
                'webhook_received' => $payment->webhooks->count() > 0,
                'events_recorded' => $payment->events->count() > 0,
            ];

            $totalDataPoints += count($dataPoints);
            $capturedDataPoints += count(array_filter($dataPoints));
        }

        return $totalDataPoints > 0 ? round(($capturedDataPoints / $totalDataPoints) * 100, 1) : 100.0;
    }
}
