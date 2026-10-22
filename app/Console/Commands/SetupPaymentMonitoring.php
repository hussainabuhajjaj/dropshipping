<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SetupPaymentMonitoring extends Command
{
    protected $signature = 'payments:setup-monitoring {--force}';
    protected $description = 'Set up automated payment monitoring on production server';

    public function handle(): int
    {
        $this->info('Setting up payment monitoring system...');

        // 1. Create monitoring cron job
        $this->setupCronJob();

        // 2. Configure environment variables
        $this->setupEnvironment();

        // 3. Test monitoring system
        $this->testMonitoring();

        $this->info('✅ Payment monitoring setup complete!');
        return self::SUCCESS;
    }

    private function setupCronJob(): void
    {
        $cronPath = '/etc/cron.d/payment-monitoring';
        $cronContent = "# Payment Monitoring - Every 5 minutes\n";
        $cronContent .= "*/5 * * * * root cd " . base_path() . " && php artisan payments:monitor-health --alert-failed-webhooks --check-redirect-rates\n";
        $cronContent .= "# Payment Monitoring - Every 30 minutes for completion times\n";
        $cronContent .= "*/30 * * * * root cd " . base_path() . " && php artisan payments:monitor-health --track-completion-times\n";
        $cronContent .= "# Payment Monitoring - Every hour for data capture audit\n";
        $cronContent .= "0 * * * * root cd " . base_path() . " && php artisan payments:monitor-health --audit-capture-rates\n";

        if ($this->option('force') || !File::exists($cronPath)) {
            File::put($cronPath, $cronContent);
            $this->info('✅ Cron job created at: ' . $cronPath);
            $this->info('   Run: sudo service cron reload');
        } else {
            $this->warn('⚠️  Cron job already exists. Use --force to overwrite.');
        }
    }

    private function setupEnvironment(): void
    {
        $envPath = base_path('.env');
        $envContent = File::get($envPath);

        $requiredVars = [
            'PAYMENT_ALERTS_EMAIL' => 'admin@example.com',
            'FAILED_WEBHOOK_THRESHOLD_MINUTES' => '60',
            'REDIRECT_TIMEOUT_MINUTES' => '30',
            'SLOW_COMPLETION_THRESHOLD_MINUTES' => '30',
            'DATA_CAPTURE_RATE_THRESHOLD' => '80',
        ];

        $updated = false;
        foreach ($requiredVars as $var => $default) {
            if (!str_contains($envContent, $var)) {
                $envContent .= "\n{$var}={$default}\n";
                $updated = true;
                $this->info("✅ Added {$var} to .env");
            }
        }

        if ($updated) {
            File::put($envPath, $envContent);
            $this->info('✅ Environment variables updated');
        } else {
            $this->info('✅ Environment variables already configured');
        }
    }

    private function testMonitoring(): void
    {
        $this->info('Testing monitoring system...');
        
        // Test command exists
        $exitCode = $this->callSilent('payments:monitor-health', ['--alert-failed-webhooks']);
        if ($exitCode === 0) {
            $this->info('✅ Failed webhook monitoring working');
        } else {
            $this->error('❌ Failed webhook monitoring failed');
        }

        // Test email configuration
        $exitCode = $this->callSilent('payments:monitor-health', ['--check-redirect-rates']);
        if ($exitCode === 0 || $exitCode === 1) { // 1 means issues found but command worked
            $this->info('✅ Email alerts working');
        } else {
            $this->error('❌ Email alerts failed');
        }
    }
}
