<?php

declare(strict_types=1);

namespace App\Domain\Support\Services;

use App\Domain\Support\Models\SupportConversation;
use App\Domain\Support\Models\SupportMessage;
use App\Models\Customer;
use App\Models\User;
use App\Notifications\Support\AdminSupportConversationAlert;
use App\Notifications\Support\CustomerSupportReplyNotification;
use App\Services\AI\DeepSeekClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SupportChatService
{
    public function __construct(private ?DeepSeekClient $deepSeek = null)
    {
    }

    /**
     * @param array<string, mixed> $context
     * @return array{conversation: SupportConversation, agent_type: 'ai'|'human', welcome: string}
     */
    public function startConversation(
        Customer $customer,
        string $requestedAgent = 'auto',
        string $channel = 'mobile',
        array $context = []
    ): array {
        $requestedAgent = $this->normalizeAgent($requestedAgent);
        $notifyAdmins = false;
        $notifyReason = 'New support conversation is waiting for an agent.';
        $conversation = SupportConversation::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['open', 'pending_agent', 'pending_customer'])
            ->latest('updated_at')
            ->first();

        if (! $conversation) {
            $agentType = $this->resolveAgentType($requestedAgent);

            $conversation = SupportConversation::create([
                'uuid' => (string) Str::uuid(),
                'customer_id' => $customer->id,
                'channel' => $channel,
                'status' => $agentType === 'human' ? 'pending_agent' : 'open',
                'requested_agent' => $requestedAgent,
                'active_agent' => $agentType,
                'ai_enabled' => $agentType === 'ai',
                'handoff_requested' => $agentType === 'human',
                'topic' => isset($context['topic']) ? (string) $context['topic'] : null,
                'tags' => isset($context['tags']) && is_array($context['tags']) ? array_values($context['tags']) : null,
                'context' => $context !== [] ? $context : null,
                'last_message_at' => now(),
            ]);

            $notifyAdmins = $agentType === 'human';
        } else {
            $agentType = $conversation->active_agent === 'human' ? 'human' : 'ai';

            if ($requestedAgent === 'human' && $conversation->active_agent !== 'human') {
                $agentType = 'human';
                $conversation->update([
                    'requested_agent' => 'human',
                    'active_agent' => 'human',
                    'ai_enabled' => false,
                    'handoff_requested' => true,
                    'status' => 'pending_agent',
                    'last_message_at' => now(),
                ]);

                $notifyAdmins = true;
                $notifyReason = 'Customer requested a human agent.';
            }
        }

        $welcome = $this->welcomeText($agentType);

        if (! $conversation->messages()->exists()) {
            $this->createSystemMessage(
                $conversation,
                $welcome,
                ['event' => 'chat_started', 'agent_type' => $agentType]
            );
        }

        if ($notifyAdmins) {
            $this->notifyAdmins($conversation, $notifyReason);
        }

        return [
            'conversation' => $conversation->fresh(),
            'agent_type' => $agentType,
            'welcome' => $welcome,
        ];
    }

    public function receiveCustomerMessage(
        SupportConversation $conversation,
        Customer $customer,
        string $body,
        array $metadata = []
    ): SupportMessage {
        $message = $conversation->messages()->create([
            'sender_type' => 'customer',
            'sender_customer_id' => $customer->id,
            'message_type' => 'text',
            'body' => trim($body),
            'metadata' => $metadata ?: null,
        ]);

        $conversation->update([
            'last_message_at' => now(),
            'last_customer_message_at' => now(),
            'resolved_at' => null,
            'status' => $conversation->active_agent === 'human' ? 'pending_agent' : 'open',
        ]);

        return $message;
    }

    /**
     * @return array{agent_type: 'ai'|'human', reply: string, messages: array<int, SupportMessage>}
     */
    public function replyToCustomer(
        SupportConversation $conversation,
        Customer $customer,
        string $input
    ): array {
        $customerMessage = $this->receiveCustomerMessage($conversation, $customer, $input);

        if ($this->shouldHandoffToHuman($input)) {
            $system = $this->requestHumanHandoff($conversation, 'Customer requested a human agent.');

            return [
                'agent_type' => 'human',
                'reply' => $system->body,
                'messages' => [$customerMessage, $system],
            ];
        }

        $ruleBased = $this->ruleBasedReply($input);
        if ($ruleBased !== null) {
            $aiMessage = $this->createAgentMessage($conversation, 'ai', $ruleBased, ['source' => 'rule']);

            return [
                'agent_type' => 'ai',
                'reply' => $aiMessage->body,
                'messages' => [$customerMessage, $aiMessage],
            ];
        }

        if ($this->canUseAi($conversation)) {
            try {
                $reply = $this->generateAiReply($conversation, $input, $customer);
                $aiMessage = $this->createAgentMessage($conversation, 'ai', $reply, ['source' => 'deepseek']);

                return [
                    'agent_type' => 'ai',
                    'reply' => $aiMessage->body,
                    'messages' => [$customerMessage, $aiMessage],
                ];
            } catch (\Throwable $exception) {
                logger()->warning('Support AI reply failed, using fallback', [
                    'conversation_id' => $conversation->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $fallback = 'Thanks for your message. A support agent will review this shortly.';
        $system = $this->requestHumanHandoff($conversation, 'AI unavailable or fallback required.', $fallback);

        return [
            'agent_type' => 'human',
            'reply' => $system->body,
            'messages' => [$customerMessage, $system],
        ];
    }

    /**
     * @return array{ack: string, messages: array<int, SupportMessage>}
     */
    public function forwardToHuman(
        SupportConversation $conversation,
        Customer $customer,
        string $message
    ): array {
        $customerMessage = $this->receiveCustomerMessage($conversation, $customer, $message, ['source' => 'forward']);
        $system = $this->requestHumanHandoff($conversation, 'Customer forwarded message to human agent.');

        return [
            'ack' => $system->body,
            'messages' => [$customerMessage, $system],
        ];
    }

    public function addAdminReply(
        SupportConversation $conversation,
        User $admin,
        string $body,
        bool $internalNote = false
    ): SupportMessage {
        $message = $conversation->messages()->create([
            'sender_type' => $internalNote ? 'system' : 'agent',
            'sender_user_id' => $admin->id,
            'message_type' => 'text',
            'body' => trim($body),
            'is_internal_note' => $internalNote,
            'metadata' => $internalNote ? ['type' => 'internal_note'] : ['type' => 'agent_reply'],
        ]);

        if (! $internalNote) {
            $conversation->update([
                'assigned_user_id' => $admin->id,
                'active_agent' => 'human',
                'ai_enabled' => false,
                'handoff_requested' => false,
                'status' => 'pending_customer',
                'last_message_at' => now(),
                'last_agent_message_at' => now(),
            ]);

            $this->notifyCustomer($conversation, $message);
        }

        $this->markMessagesReadByAdmin($conversation);

        return $message;
    }

    public function assignToAdmin(SupportConversation $conversation, User $admin): void
    {
        $conversation->update([
            'assigned_user_id' => $admin->id,
            'active_agent' => 'human',
            'ai_enabled' => false,
        ]);
    }

    public function addAiSummaryInternalNote(SupportConversation $conversation, User $admin): SupportMessage
    {
        $summary = $this->buildConversationSummary($conversation);

        return $conversation->messages()->create([
            'sender_type' => 'system',
            'sender_user_id' => $admin->id,
            'message_type' => 'text',
            'body' => $summary,
            'is_internal_note' => true,
            'metadata' => [
                'type' => 'ai_summary',
                'generated_by' => $admin->id,
                'generated_at' => now()->toIso8601String(),
                'provider' => $this->deepSeek && config('services.deepseek.key') ? 'deepseek' : 'fallback',
            ],
        ]);
    }

    public function markResolved(SupportConversation $conversation): void
    {
        $conversation->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'handoff_requested' => false,
        ]);
    }

    public function markMessagesReadByCustomer(SupportConversation $conversation): int
    {
        return $conversation->messages()
            ->where('is_internal_note', false)
            ->whereNull('read_at')
            ->whereIn('sender_type', ['agent', 'ai', 'system'])
            ->update([
                'read_at' => now(),
            ]);
    }

    public function markMessagesReadByAdmin(SupportConversation $conversation): int
    {
        return $conversation->messages()
            ->where('is_internal_note', false)
            ->whereNull('read_at')
            ->where('sender_type', 'customer')
            ->update([
                'read_at' => now(),
            ]);
    }

    public function alertAdmins(SupportConversation $conversation, string $reason): void
    {
        $this->notifyAdmins($conversation, $reason);
    }

    /**
     * @return Collection<int, SupportMessage>
     */
    public function getMessages(SupportConversation $conversation, ?int $afterId = null, int $limit = 50): Collection
    {
        $limit = max(1, min($limit, 100));

        $query = $conversation->messages()
            ->where('is_internal_note', false)
            ->orderBy('id');

        if ($afterId !== null) {
            $query->where('id', '>', $afterId);
        }

        return $query->limit($limit)->get();
    }

    private function canUseAi(SupportConversation $conversation): bool
    {
        return (bool) config('services.deepseek.key')
            && $conversation->ai_enabled
            && $conversation->active_agent !== 'human';
    }

    private function normalizeAgent(string $requested): string
    {
        $requested = strtolower(trim($requested));

        return in_array($requested, ['auto', 'ai', 'human'], true) ? $requested : 'auto';
    }

    private function resolveAgentType(string $requested): string
    {
        if ($requested === 'human') {
            return 'human';
        }

        if ($requested === 'ai') {
            return config('services.deepseek.key') ? 'ai' : 'human';
        }

        return config('services.deepseek.key') ? 'ai' : 'human';
    }

    private function welcomeText(string $agentType): string
    {
        if ($agentType === 'human') {
            return 'Thanks for contacting support. A human agent will join this conversation shortly.';
        }

        return 'Hi, I am your AI support assistant. Tell me what you need, and I can help or hand off to a human agent.';
    }

    private function shouldHandoffToHuman(string $input): bool
    {
        $text = strtolower($input);

        foreach (['human', 'agent', 'representative', 'real person', 'support team'] as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function ruleBasedReply(string $input): ?string
    {
        $text = strtolower($input);

        if (str_contains($text, 'track') || str_contains($text, 'where is my order')) {
            return 'You can track your order from the Orders screen using your order number. If tracking is delayed, ask me to connect you to a human agent.';
        }

        if (str_contains($text, 'refund') || str_contains($text, 'return')) {
            return 'For returns and refunds, please share your order number and reason. I can start the process or connect you with a support agent.';
        }

        if (str_contains($text, 'payment') || str_contains($text, 'charged')) {
            return 'For payment issues, share your order number and payment method used. A support agent can verify transaction details quickly.';
        }

        return null;
    }

    private function generateAiReply(SupportConversation $conversation, string $input, Customer $customer): string
    {
        if (! $this->deepSeek) {
            return 'Thanks for your message. A support agent will review this shortly.';
        }

        $history = $conversation->messages()
            ->where('is_internal_note', false)
            ->latest('id')
            ->limit(8)
            ->get()
            ->reverse()
            ->map(function (SupportMessage $message): array {
                $role = match ($message->sender_type) {
                    'customer' => 'user',
                    'agent', 'ai', 'system' => 'assistant',
                    default => 'assistant',
                };

                return [
                    'role' => $role,
                    'content' => $message->body,
                ];
            })
            ->values()
            ->all();

        $messages = [
            [
                'role' => 'system',
                'content' => 'You are an ecommerce support assistant for Simbazu. Be concise, practical, and polite. If an issue requires human action (refund approval, payment dispute, account security), explicitly say you are handing off to a human agent.',
            ],
            [
                'role' => 'user',
                'content' => 'Customer: ' . ($customer->name ?: 'Customer') . '. Conversation channel: ' . ($conversation->channel ?: 'mobile') . '.',
            ],
            ...$history,
            [
                'role' => 'user',
                'content' => $input,
            ],
        ];

        $reply = trim($this->deepSeek->chat($messages, 0.4));

        return $reply !== '' ? $reply : 'I did not catch that. Could you share more details so I can help?';
    }

    private function buildConversationSummary(SupportConversation $conversation): string
    {
        $messages = $conversation->messages()
            ->where('is_internal_note', false)
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->reverse()
            ->values();

        if ($messages->isEmpty()) {
            return 'AI summary: No messages available yet.';
        }

        if ($this->deepSeek && config('services.deepseek.key')) {
            try {
                $transcript = $messages->map(function (SupportMessage $message): string {
                    $sender = match ($message->sender_type) {
                        'customer' => 'Customer',
                        'agent' => 'Agent',
                        'ai' => 'AI',
                        default => 'System',
                    };

                    return "{$sender}: {$message->body}";
                })->implode("\n");

                $summary = trim($this->deepSeek->chat([
                    [
                        'role' => 'system',
                        'content' => 'Summarize support conversations for internal admin notes. Keep concise and practical. Output plain text only.',
                    ],
                    [
                        'role' => 'user',
                        'content' => "Create an internal support summary with sections: Issue, Key facts, Risk level, Next action.\n\nConversation:\n{$transcript}",
                    ],
                ], 0.2));

                if ($summary !== '') {
                    return $summary;
                }
            } catch (\Throwable $exception) {
                logger()->warning('Support summary generation failed, using fallback summary', [
                    'conversation_id' => $conversation->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $lastCustomer = $messages->where('sender_type', 'customer')->last();
        $lastAgent = $messages->whereIn('sender_type', ['agent', 'ai'])->last();

        return implode("\n", array_filter([
            'AI summary (fallback):',
            'Issue: ' . ($conversation->topic ?: 'General support request'),
            'Status: ' . ($conversation->status ?: 'open'),
            $lastCustomer ? 'Last customer message: ' . Str::limit($lastCustomer->body, 220) : null,
            $lastAgent ? 'Last agent reply: ' . Str::limit($lastAgent->body, 220) : null,
            'Next action: Review conversation and provide final human response if needed.',
        ]));
    }

    private function requestHumanHandoff(
        SupportConversation $conversation,
        string $reason,
        ?string $ackText = null
    ): SupportMessage {
        $conversation->update([
            'active_agent' => 'human',
            'requested_agent' => 'human',
            'ai_enabled' => false,
            'handoff_requested' => true,
            'status' => 'pending_agent',
            'last_message_at' => now(),
            'resolved_at' => null,
        ]);

        $ackText ??= 'I am handing this conversation to a human agent now. You will get a follow-up shortly.';

        $system = $this->createSystemMessage(
            $conversation,
            $ackText,
            ['event' => 'handoff_requested', 'reason' => $reason]
        );

        $this->notifyAdmins($conversation, $reason);

        return $system;
    }

    private function createAgentMessage(
        SupportConversation $conversation,
        string $agentType,
        string $body,
        array $metadata = []
    ): SupportMessage {
        $message = $conversation->messages()->create([
            'sender_type' => $agentType === 'human' ? 'agent' : 'ai',
            'message_type' => 'text',
            'body' => trim($body),
            'metadata' => $metadata ?: null,
        ]);

        $conversation->update([
            'active_agent' => $agentType,
            'status' => 'pending_customer',
            'last_message_at' => now(),
            'last_agent_message_at' => now(),
        ]);

        return $message;
    }

    private function createSystemMessage(
        SupportConversation $conversation,
        string $body,
        array $metadata = []
    ): SupportMessage {
        return $conversation->messages()->create([
            'sender_type' => 'system',
            'message_type' => 'text',
            'body' => trim($body),
            'metadata' => $metadata ?: null,
        ]);
    }

    private function notifyAdmins(SupportConversation $conversation, string $reason): void
    {
        $admins = User::query()->supportAgents()->get();

        foreach ($admins as $admin) {
            $admin->notify(new AdminSupportConversationAlert($conversation, $reason));
        }
    }

    private function notifyCustomer(SupportConversation $conversation, SupportMessage $message): void
    {
        $customer = $conversation->customer;
        if (! $customer) {
            return;
        }

        $customer->notify(new CustomerSupportReplyNotification($conversation, $message));
    }
}
