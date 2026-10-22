<?php

declare(strict_types=1);

namespace App\Domain\Affiliates\Services;

use App\Domain\Affiliates\Models\AffiliateWithdrawal;
use Illuminate\Support\Facades\DB;

class AffiliateWithdrawalService
{
    public function processWithdrawal(AffiliateWithdrawal $withdrawal): void
    {
        if ($withdrawal->status === 'processed') {
            return;
        }

        DB::transaction(function () use ($withdrawal) {
            $withdrawal->update([
                'status' => 'processed',
                'processed_at' => now(),
            ]);

            $affiliate = $withdrawal->affiliate;
            $affiliate->decrement('balance_available', min($affiliate->balance_available, $withdrawal->amount));
        });
    }

    public function rejectWithdrawal(AffiliateWithdrawal $withdrawal, string $reason = null): void
    {
        $withdrawal->update([
            'status' => 'rejected',
        ]);
    }
}
