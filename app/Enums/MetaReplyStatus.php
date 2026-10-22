<?php

declare(strict_types=1);

namespace App\Enums;

enum MetaReplyStatus: string
{
    case Draft = 'draft';
    case Auto = 'auto';
    case Approved = 'approved';
    case Sent = 'sent';
    case Rejected = 'rejected';
    case Failed = 'failed';
}
