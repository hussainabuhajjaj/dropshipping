<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DailyInstagramMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $date,
        public string $zipPath,
        public string $txtPath,
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject("Daily Instagram Products — {$this->date}")
            ->attach($this->zipPath, ['as' => "instagram-{$this->date}.zip", 'mime' => 'application/zip'])
            ->attach($this->txtPath, ['as' => "instagram-{$this->date}.txt", 'mime' => 'text/plain'])
            ->html("<p>Today's Instagram products for <strong>{$this->date}</strong>.</p><p>See attached zip (images) and txt (content).</p>");
    }
}
