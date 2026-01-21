<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactMessageMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param array{name:string,email:string,subject:string,message:string} $payload
     */
    public function __construct(public array $payload)
    {
    }

    public function build(): self
    {
        $subjectLine = 'New contact message: ' . ($this->payload['subject'] ?? 'Contact request');
        $bodyHtml = view('emails.contact-message-body', [
            'name' => $this->payload['name'] ?? 'Customer',
            'email' => $this->payload['email'] ?? null,
            'subject' => $this->payload['subject'] ?? null,
            'messageBody' => $this->payload['message'] ?? '',
        ])->render();

        $mail = $this->subject($subjectLine)
            ->view('emails.base', [
                'title' => 'New contact request',
                'preheader' => $this->payload['subject'] ?? 'A customer submitted a contact message.',
                'bodyHtml' => $bodyHtml,
                'actionUrl' => !empty($this->payload['email']) ? 'mailto:' . $this->payload['email'] : null,
                'actionLabel' => 'Reply to customer',
            ]);

        if (!empty($this->payload['email'])) {
            $mail->replyTo($this->payload['email'], $this->payload['name'] ?? null);
        }

        return $mail;
    }
}
