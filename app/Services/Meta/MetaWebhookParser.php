<?php

declare(strict_types=1);

namespace App\Services\Meta;

use App\Models\MetaInboxMessage;
use App\Models\MetaReply;
use Illuminate\Support\Facades\Log;

class MetaWebhookParser
{
    public function __construct(
        private MetaReplyService $replyService,
        private DispatchMetaReply $dispatcher,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     */
    public function process(array $entries): int
    {
        $count = 0;

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];
            $messaging = $entry['messaging'] ?? [];

            foreach ($changes as $change) {
                $value = $change['value'] ?? [];
                $field = $change['field'] ?? '';

                if ($field === 'comments') {
                    if ($this->ingestComment($value)) {
                        $count++;
                    }
                }
            }

            foreach ($messaging as $event) {
                if ($this->ingestMessaging($event)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function ingestComment(array $value): bool
    {
        $commentId = (string) ($value['id'] ?? '');
        if ($commentId === '') {
            return false;
        }

        $from = $value['from'] ?? [];

        $message = MetaInboxMessage::firstOrCreate(
            ['platform' => 'instagram', 'channel' => 'comment', 'external_id' => $commentId],
            [
                'sender_id' => (string) ($from['id'] ?? ''),
                'sender_handle' => (string) ($from['username'] ?? ''),
                'sender_name' => (string) ($from['username'] ?? ''),
                'text' => (string) ($value['text'] ?? ''),
                'media_type' => (string) ($value['media']['image_url'] ?? '') !== '' ? 'image' : null,
                'media_url' => (string) ($value['media']['image_url'] ?? ''),
                'parent_id' => (string) ($value['parent_id'] ?? ''),
                'raw_payload' => $value,
                'received_at' => now(),
            ],
        );

        return $this->handleInboxMessage($message);
    }

    private function ingestMessaging(array $event): bool
    {
        $messageData = $event['message'] ?? null;
        if (! is_array($messageData)) {
            return false;
        }

        $messageId = (string) ($messageData['mid'] ?? '');
        if ($messageId === '') {
            return false;
        }

        $sender = $event['sender'] ?? [];

        $message = MetaInboxMessage::firstOrCreate(
            ['platform' => 'instagram', 'channel' => 'message', 'external_id' => $messageId],
            [
                'sender_id' => (string) ($sender['id'] ?? ''),
                'text' => (string) ($messageData['text'] ?? ''),
                'recipient_id' => (string) ($event['recipient']['id'] ?? ''),
                'raw_payload' => $event,
                'received_at' => now(),
            ],
        );

        return $this->handleInboxMessage($message);
    }

    private function handleInboxMessage(MetaInboxMessage $message): bool
    {
        try {
            $reply = $this->replyService->handle($message);
            ($this->dispatcher)($reply);
            return true;
        } catch (\Throwable $e) {
            Log::warning('Meta inbox message handling failed', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
