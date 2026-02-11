<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class QueueHealthReportMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param array<string, mixed> $report
     */
    public function __construct(public array $report)
    {
    }

    public function build(): self
    {
        $bodyHtml = view('emails.queue-health-report-body', [
            'report' => $this->report,
        ])->render();

        $appName = (string) config('app.name', 'App');
        $period = (string) ($this->report['period_label'] ?? '');

        return $this->subject("[{$appName}] Queue health report {$period}")
            ->view('emails.base', [
                'title' => 'Queue Health Report',
                'preheader' => "Queue report {$period}",
                'bodyHtml' => $bodyHtml,
                'actionUrl' => null,
                'actionLabel' => null,
            ]);
    }
}

