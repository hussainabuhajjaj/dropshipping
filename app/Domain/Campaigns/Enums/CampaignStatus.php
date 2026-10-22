<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Enums;

enum CampaignStatus: string
{
    case DRAFT = 'draft';
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED = 'approved';
    case SCHEDULED = 'scheduled';
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case REJECTED = 'rejected';
    case ENDED = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENDING_APPROVAL => 'Pending Approval',
            self::APPROVED => 'Approved',
            self::SCHEDULED => 'Scheduled',
            self::ACTIVE => 'Active',
            self::PAUSED => 'Paused',
            self::REJECTED => 'Rejected',
            self::ENDED => 'Ended',
        };
    }

    /**
     * Statuses under which the campaign is visible on the storefront.
     */
    public function isVisible(): bool
    {
        return in_array($this, [self::APPROVED, self::SCHEDULED, self::ACTIVE], true);
    }

    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->toArray();
    }
}
