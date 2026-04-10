<?php

namespace App\Console\Commands;

use App\Services\PaymentMonitoringService;
use Illuminate\Console\Command;

class MonitorPaymentHealth extends Command
{
    protected $signature = 'payments:monitor-health {--alert-failed-webhooks} {--check-redirect-rates} {--track-completion-times} {--audit-capture-rates}';
    
    protected $description = 'Monitor payment health and send alerts for issues';

    public function handle(PaymentMonitoringService $monitoringService): int
    {
        $this->info('Starting payment health monitoring...');
        
        $totalAlerts = 0;

        if ($this->option('alert-failed-webhooks')) {
            $this->line('Checking for failed webhooks...');
            $webhookAlerts = $monitoringService->monitorFailedWebhooks();
            $totalAlerts += count($webhookAlerts);
            
            foreach ($webhookAlerts as $alert) {
                $this->error("🚨 WEBHOOK FAILURE: {$alert['message']}");
            }
        }

        if ($this->option('check-redirect-rates')) {
            $this->line('Checking redirect completion rates...');
            $redirectAlerts = $monitoringService->monitorRedirectRates();
            $totalAlerts += count($redirectAlerts);
            
            foreach ($redirectAlerts as $alert) {
                $severity = $alert['severity'] === 'critical' ? '🚨' : '⚠️';
                $this->line("{$severity} REDIRECT ISSUE: {$alert['message']}");
            }
        }

        if ($this->option('track-completion-times')) {
            $this->line('Tracking payment completion times...');
            $completionAlerts = $monitoringService->trackPaymentCompletionTimes();
            $totalAlerts += count($completionAlerts);
            
            foreach ($completionAlerts as $alert) {
                $this->line("⏱️  COMPLETION ISSUE: {$alert['message']}");
            }
        }

        if ($this->option('audit-capture-rates')) {
            $this->line('Auditing data capture rates...');
            $auditAlerts = $monitoringService->auditDataCaptureRates();
            $totalAlerts += count($auditAlerts);
            
            foreach ($auditAlerts as $alert) {
                $severity = $alert['severity'] === 'critical' ? '🚨' : '⚠️';
                $this->line("{$severity} DATA CAPTURE: {$alert['message']}");
            }
        }

        if ($totalAlerts === 0) {
            $this->info('✅ All payment health checks passed - no issues detected');
        } else {
            $this->error("❌ {$totalAlerts} payment health issues detected and alerts sent");
        }

        return $totalAlerts === 0 ? self::SUCCESS : self::FAILURE;
    }
}
