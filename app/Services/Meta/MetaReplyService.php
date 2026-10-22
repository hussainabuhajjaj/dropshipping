<?php

declare(strict_types=1);

namespace App\Services\Meta;

use App\Models\MetaInboxMessage;
use App\Models\MetaReply;
use App\Services\AI\DeepSeekClient;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MetaReplyService
{
    public function __construct(
        private DeepSeekClient $deepseek,
        private MetaGraphApiService $graph,
    ) {
    }

    /**
     * Determine whether this message can be auto-answered (safe topics) or
     * requires a human-approved draft.
     */
    public function classify(string $text): string
    {
        $text = strtolower(trim($text));

        if (preg_match('/\b(refund|return|complaint|problem|damaged|wrong item|tracking|where is my|angry|scam)\b/', $text)) {
            return 'escalate';
        }

        // Greetings / product questions are safe to auto-reply.
        if (preg_match('/\b(hello|hi|hey|how much|price|cost|sizes?|delivery|shipping|how long|link|buy|order|whatsapp)\b/', $text)) {
            return 'safe';
        }

        if (preg_match('/\b(spam|offers?|discount code|affiliate|free|promo)\b/', $text)) {
            return 'spam';
        }

        return 'other';
    }

    public function handle(MetaInboxMessage $message): ?MetaReply
    {
        $text = $message->text ?? '';

        if (trim($text) === '') {
            return null;
        }

        $classification = $this->classify($text);

        try {
            $draft = $this->generateDraft($message, $classification);
        } catch (RuntimeException $e) {
            Log::warning('Meta reply draft generation failed', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);

            return $this->recordReply($message, null, $classification, false, $e->getMessage());
        }

        $autoSend = $classification === 'safe' && (bool) config('services.meta.auto_reply', true);

        return $this->recordReply($message, $draft, $classification, $autoSend);
    }

    private function recordReply(
        MetaInboxMessage $message,
        ?string $draft,
        string $classification,
        bool $autoSend,
        ?string $error = null,
    ): MetaReply {
        $status = $error !== null ? 'failed' : ($autoSend ? 'auto' : 'draft');

        return MetaReply::updateOrCreate(
            ['message_id' => $message->id],
            [
                'draft_text' => $draft,
                'classification' => $classification,
                'auto_send' => $autoSend,
                'status' => $status,
                'error' => $error !== null ? substr($error, 0, 1000) : null,
            ],
        );
    }

    public function generateDraft(MetaInboxMessage $message, string $classification): string
    {
        $storeName = config('app.name', 'Simbazu');
        $text = $message->text ?? '';

        $system = "You are the customer service representative for {$storeName}, an online fashion store. "
            . "Keep replies short (1-3 sentences), warm, and helpful. "
            . "For product questions, mention browsing the store and that we can help with sizes and delivery. "
            . "Never invent prices, stock levels, delivery times, discounts, or policies. "
            . "For shipping questions, say delivery typically takes 5-10 business days and we confirm tracking after dispatch. "
            . "For greetings, welcome them warmly and ask if they have a question. "
            . "For spam, do not engage — output nothing. "
            . "Reply in the same language the customer used.";

        $result = $this->deepseek->chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $classification === 'spam'
                ? 'This is spam: "' . $text . '". Output nothing.'
                : 'Customer message: "' . $text . '"'],
        ], 0.5);

        return $result;
    }
}
