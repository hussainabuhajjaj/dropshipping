<?php

declare(strict_types=1);

namespace App\Services\Meta;

use App\Enums\MetaReplyStatus;
use App\Jobs\SendMetaReplyJob;
use App\Models\MetaReply;
use Illuminate\Support\Facades\Log;

class DispatchMetaReply
{
    public function __invoke(?MetaReply $reply): void
    {
        if (! $reply) {
            return;
        }

        if ($reply->auto_send || $reply->status === MetaReplyStatus::Approved) {
            SendMetaReplyJob::dispatch($reply->id)->onConnection('redis');
        }
    }
}
