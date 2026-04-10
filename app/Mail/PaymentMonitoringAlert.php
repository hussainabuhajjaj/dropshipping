<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentMonitoringAlert extends Mailable
{
    use Queueable, SerializesModels;

    public array $alert;

    public function __construct(array $alert)
    {
        $this->alert = $alert;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Payment Alert] ' . ucfirst($this->alert['type']) . ' - ' . $this->alert['severity'],
            to: config('payment.monitoring.alerts_email', ['admin@example.com']),
        );
    }

    public function content(): string
    {
        $severity = strtoupper($this->alert['severity']);
        $type = strtoupper($this->alert['type']);
        
        return "
PAYMENT MONITORING ALERT
==========================

Severity: {$severity}
Type: {$type}
Time: {$this->alert['created_at']}

Message:
{$this->alert['message']}

Details:
" . $this->formatAlertDetails() . "

Action Required:
- Review the payment details below
- Investigate the root cause
- Take corrective action if needed

---
This is an automated alert from your payment monitoring system.
        ";
    }

    private function formatAlertDetails(): string
    {
        $details = '';

        switch ($this->alert['type']) {
            case 'webhook_failure':
                $details .= "Webhook ID: {$this->alert['data']['webhook_id']}\n";
                $details .= "Payment ID: {$this->alert['data']['payment_id']}\n";
                $details .= "Provider: {$this->alert['data']['provider']}\n";
                $details .= "Event ID: {$this->alert['data']['external_event_id']}\n";
                break;

            case 'redirect_issue':
                $details .= "Order Number: {$this->alert['data']['order_number']}\n";
                $details .= "Payment ID: {$this->alert['data']['payment_id']}\n";
                $details .= "Provider Reference: {$this->alert['data']['provider_reference']}\n";
                $details .= "Minutes Since Init: {$this->alert['data']['minutes_since_init']}\n";
                $details .= "Issue Type: {$this->alert['data']['issue']}\n";
                break;

            case 'slow_completion':
                $details .= "Average Completion Time: {$this->alert['data']['avg_completion_minutes']} minutes\n";
                $details .= "Total Completed: {$this->alert['data']['total_completed']}\n";
                $details .= "Min Time: {$this->alert['data']['min_completion_minutes']} minutes\n";
                $details .= "Max Time: {$this->alert['data']['max_completion_minutes']} minutes\n";
                break;

            case 'poor_data_capture':
                $details .= "Order Number: {$this->alert['data']['order_number']}\n";
                $details .= "Payment ID: {$this->alert['data']['payment_id']}\n";
                $details .= "Capture Rate: {$this->alert['data']['capture_rate']}%\n";
                $details .= "Missing Points: " . implode(', ', $this->alert['data']['missing_points']) . "\n";
                break;

            default:
                $details .= json_encode($this->alert['data'], JSON_PRETTY_PRINT);
                break;
        }

        return $details;
    }
}
