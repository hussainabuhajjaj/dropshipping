<?php

namespace App\Enums;

enum ChargebackStatus: string
{
    case OPENED = 'opened';
    case AWAITING_EVIDENCE = 'awaiting_evidence';
    case EVIDENCE_SUBMITTED = 'evidence_submitted';
    case UNDER_REVIEW = 'under_review';
    case WON = 'won';
    case LOST = 'lost';
    case SETTLED = 'settled';
    case WITHDRAWN = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::OPENED => 'Opened',
            self::AWAITING_EVIDENCE => 'Awaiting Evidence',
            self::EVIDENCE_SUBMITTED => 'Evidence Submitted',
            self::UNDER_REVIEW => 'Under Review',
            self::WON => 'Won',
            self::LOST => 'Lost',
            self::SETTLED => 'Settled',
            self::WITHDRAWN => 'Withdrawn',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OPENED, self::AWAITING_EVIDENCE => 'warning',
            self::EVIDENCE_SUBMITTED, self::UNDER_REVIEW => 'info',
            self::WON, self::SETTLED => 'success',
            self::LOST => 'danger',
            self::WITHDRAWN => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::OPENED => 'heroicon-o-exclamation-circle',
            self::AWAITING_EVIDENCE => 'heroicon-o-document-text',
            self::EVIDENCE_SUBMITTED => 'heroicon-o-check-circle',
            self::UNDER_REVIEW => 'heroicon-o-clock',
            self::WON => 'heroicon-o-check-badge',
            self::LOST => 'heroicon-o-x-circle',
            self::SETTLED => 'heroicon-o-handshake',
            self::WITHDRAWN => 'heroicon-o-minus-circle',
        };
    }

    public function isResolved(): bool
    {
        return in_array($this, [self::WON, self::LOST, self::SETTLED, self::WITHDRAWN]);
    }

    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }
}
