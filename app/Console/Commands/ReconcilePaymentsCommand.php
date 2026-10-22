<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ReconcilePendingPayments;
use Illuminate\Console\Command;

class ReconcilePaymentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:reconcile {--force : Force reconciliation immediately}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile pending payments with Paystack';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting payment reconciliation...');

        if ($this->option('force')) {
            $this->warn('Force mode: reconciling all pending payments immediately');
        }

        // Dispatch the job
        ReconcilePendingPayments::dispatch();

        $this->info('Payment reconciliation job dispatched successfully!');

        return Command::SUCCESS;
    }
}
