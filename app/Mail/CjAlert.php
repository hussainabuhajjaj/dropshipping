<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CjAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $title, public array $context = [])
    {
    }

    public function build()
    {
        return $this->subject('CJ Alert')->text('emails.cj_alert')->with([
            'title' => $this->title,
            'context' => $this->context,
        ]);
    }
}
