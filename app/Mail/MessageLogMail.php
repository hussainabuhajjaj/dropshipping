<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Messaging\Models\MessageLog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class MessageLogMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public MessageLog $log)
    {
    }

    public function build(): self
    {
        $subject = $this->log->subject ?: ('Message from ' . config('app.name'));
        $rawBody = $this->log->message_content ?? '';
        $bodyHtml = Str::contains($rawBody, ['<', '>'])
            ? $rawBody
            : nl2br(e($rawBody));

        $preheader = Str::limit(trim(strip_tags($bodyHtml)), 120);

        return $this->subject($subject)
            ->view('emails.base', [
                'title' => $subject,
                'preheader' => $preheader,
                'bodyHtml' => $bodyHtml,
            ]);
    }
}
