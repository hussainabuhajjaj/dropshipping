<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\MetaReplyStatus;
use App\Models\MetaInboxMessage;
use App\Models\MetaReply;
use App\Services\Meta\MetaGraphApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendMetaReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public readonly int $replyId)
    {
    }

    public function handle(MetaGraphApiService $graph): void
    {
        $reply = MetaReply::find($this->replyId);

        if (! $reply || $reply->status === MetaReplyStatus::Sent) {
            return;
        }

        if ($reply->status !== MetaReplyStatus::Approved && ! $reply->auto_send) {
            Log::info('Meta reply skipped (not approved / not auto)', ['reply_id' => $reply->id]);
            return;
        }

        $text = trim((string) $reply->draft_text);
        if ($text === '') {
            $reply->update(['status' => MetaReplyStatus::Rejected]);
            return;
        }

        $message = MetaInboxMessage::find($reply->message_id);
        if (! $message) {
            $reply->update(['status' => MetaReplyStatus::Failed]);
            return;
        }

        $sent = $message->channel === 'message'
            ? $graph->sendInstagramPrivateReply((string) $message->sender_id, $text)
            : $graph->sendCommentReply((string) $message->external_id, $text);

        if ($sent) {
            $reply->update([
                'status' => MetaReplyStatus::Sent,
                'sent_at' => now(),
                'error' => null,
            ]);
        } else {
            $reply->update(['status' => MetaReplyStatus::Failed]);
        }
    }
}
