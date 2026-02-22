<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Affiliates\Models\AffiliateCommission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AffiliateCommissionEarned extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly AffiliateCommission $commission,
    ) {
    }

    public function build(): static
    {
        return $this
            ->subject('New commission from ' . config('app.name'))
            ->view('emails.affiliate.commission-earned')
            ->with([
                'commission' => $this->commission,
            ]);
    }
}
