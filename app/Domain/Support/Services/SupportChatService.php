<?php

declare(strict_types=1);

namespace App\Domain\Support\Services;

use App\Events\Support\SupportMessageCreated;
use App\Domain\Support\Models\SupportConversation;
use App\Domain\Support\Models\SupportMessage;
use App\Models\Customer;
use App\Models\Order;
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

            if ($this->isAiOnlyMode() && $conversation->active_agent !== 'ai') {
                $agentType = 'ai';
                $conversation->update([
                    'requested_agent' => 'ai',
                    'active_agent' => 'ai',
                    'ai_enabled' => true,
                    'handoff_requested' => false,
                    'status' => 'open',
                    'last_message_at' => now(),
                ]);
            } elseif ($requestedAgent === 'human' && $conversation->active_agent !== 'human') {
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
        array $metadata = [],
        string $messageType = 'text'
    ): SupportMessage {
        $message = $conversation->messages()->create([
            'sender_type' => 'customer',
            'sender_customer_id' => $customer->id,
            'message_type' => $messageType,
            'body' => trim($body),
            'metadata' => $metadata ?: null,
        ]);

        $conversation->update([
            'last_message_at' => now(),
            'last_customer_message_at' => now(),
            'resolved_at' => null,
            'status' => $conversation->active_agent === 'human' ? 'pending_agent' : 'open',
        ]);

        $this->dispatchRealtimeMessage($conversation, $message);

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

        $ruleBased = $this->ruleBasedReply($conversation, $customer, $input);
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

        if ($this->isAiOnlyMode()) {
            $fallback = $this->buildOrderPaymentReply($customer, $input)
                ?? 'Thanks for your message. Please share your order number if you want me to review order or payment updates.';
            $aiMessage = $this->createAgentMessage($conversation, 'ai', $fallback, ['source' => 'fallback']);

            return [
                'agent_type' => 'ai',
                'reply' => $aiMessage->body,
                'messages' => [$customerMessage, $aiMessage],
            ];
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
        if ($this->isAiOnlyMode()) {
            $result = $this->replyToCustomer($conversation, $customer, $message);

            return [
                'ack' => $result['reply'],
                'messages' => $result['messages'],
            ];
        }

        $customerMessage = $this->receiveCustomerMessage($conversation, $customer, $message, ['source' => 'forward']);
        $system = $this->requestHumanHandoff($conversation, 'Customer forwarded message to human agent.');

        return [
            'ack' => $system->body,
            'messages' => [$customerMessage, $system],
        ];
    }

    /**
     * @param array<string, mixed> $attachmentMetadata
     * @return array{ack: string, messages: array<int, SupportMessage>}
     */
    public function forwardAttachmentToHuman(
        SupportConversation $conversation,
        Customer $customer,
        array $attachmentMetadata,
        ?string $caption = null
    ): array {
        $messageType = (string) ($attachmentMetadata['attachment_type'] ?? 'file');
        if (! in_array($messageType, ['image', 'file'], true)) {
            $messageType = 'file';
        }

        $name = (string) ($attachmentMetadata['attachment_name'] ?? 'Attachment');
        $body = trim((string) ($caption ?? ''));
        if ($body === '') {
            $body = $messageType === 'image' ? "Image: {$name}" : "File: {$name}";
        }

        $customerMessage = $this->receiveCustomerMessage(
            $conversation,
            $customer,
            $body,
            $attachmentMetadata + ['source' => 'attachment'],
            $messageType
        );

        if ($this->isAiOnlyMode()) {
            $aiPrompt = $caption !== null && trim($caption) !== ''
                ? $caption
                : ($messageType === 'image'
                    ? 'Customer shared an image and needs support.'
                    : 'Customer shared a file and needs support.');

            $ruleBased = $this->ruleBasedReply($conversation, $customer, $aiPrompt);
            if ($ruleBased !== null) {
                $aiMessage = $this->createAgentMessage($conversation, 'ai', $ruleBased, ['source' => 'rule']);
            } elseif ($this->canUseAi($conversation)) {
                try {
                    $reply = $this->generateAiReply($conversation, $aiPrompt, $customer);
                    $aiMessage = $this->createAgentMessage($conversation, 'ai', $reply, ['source' => 'deepseek']);
                } catch (\Throwable $exception) {
                    logger()->warning('Support AI reply failed for attachment, using fallback', [
                        'conversation_id' => $conversation->id,
                        'error' => $exception->getMessage(),
                    ]);

                    $aiMessage = $this->createAgentMessage(
                        $conversation,
                        'ai',
                        'I received your attachment. Please share your order number so I can check the latest status for you.',
                        ['source' => 'fallback']
                    );
                }
            } else {
                $aiMessage = $this->createAgentMessage(
                    $conversation,
                    'ai',
                    'I received your attachment. Please share your order number so I can check the latest status for you.',
                    ['source' => 'fallback']
                );
            }

            return [
                'ack' => $aiMessage->body,
                'messages' => [$customerMessage, $aiMessage],
            ];
        }

        $system = $this->requestHumanHandoff($conversation, 'Customer sent an attachment to human support.');

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
        $this->dispatchRealtimeMessage($conversation, $message);

        return $message;
    }

    /**
     * @param array<string, mixed> $attachmentMetadata
     */
    public function addAdminAttachmentReply(
        SupportConversation $conversation,
        User $admin,
        array $attachmentMetadata,
        ?string $caption = null,
        bool $internalNote = false
    ): SupportMessage {
        $messageType = (string) ($attachmentMetadata['attachment_type'] ?? 'file');
        if (! in_array($messageType, ['image', 'file'], true)) {
            $messageType = 'file';
        }

        $name = (string) ($attachmentMetadata['attachment_name'] ?? 'Attachment');
        $body = trim((string) ($caption ?? ''));
        if ($body === '') {
            $body = $messageType === 'image' ? "Image: {$name}" : "File: {$name}";
        }

        $message = $conversation->messages()->create([
            'sender_type' => $internalNote ? 'system' : 'agent',
            'sender_user_id' => $admin->id,
            'message_type' => $messageType,
            'body' => $body,
            'metadata' => $attachmentMetadata + [
                'type' => $internalNote ? 'internal_note_attachment' : 'agent_attachment',
            ],
            'is_internal_note' => $internalNote,
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
        $this->dispatchRealtimeMessage($conversation, $message);

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

        $lastMessage = $conversation->messages()->latest('id')->first();
        $lastMessageEvent = is_array($lastMessage?->metadata)
            ? (string) ($lastMessage->metadata['event'] ?? '')
            : '';
        if ($lastMessageEvent !== 'session_resolved') {
            $this->createSystemMessage(
                $conversation,
                'This support session is now resolved. Please start a new support session for a new issue.',
                [
                    'event' => 'session_resolved',
                    'requires_new_session' => true,
                ]
            );
        }
    }

    public function requiresNewSession(SupportConversation $conversation): bool
    {
        return in_array((string) $conversation->status, ['resolved', 'closed'], true);
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
        if ($this->isAiOnlyMode()) {
            return 'ai';
        }

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

        if ($this->isAiOnlyMode()) {
            return 'Hi, I am your AI support assistant. Share your issue or order number and I will provide the latest updates here.';
        }

        return 'Hi, I am your AI support assistant. Tell me what you need, and I can help or hand off to a human agent.';
    }

    private function shouldHandoffToHuman(string $input): bool
    {
        if ($this->isAiOnlyMode()) {
            return false;
        }

        $text = strtolower($input);

        foreach (['human', 'agent', 'representative', 'real person', 'support team'] as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function ruleBasedReply(SupportConversation $conversation, Customer $customer, string $input): ?string
    {
        $text = strtolower($input);
        $orderPaymentReply = $this->buildOrderPaymentReply($customer, $input);
        if ($orderPaymentReply !== null) {
            return $orderPaymentReply;
        }

        if (str_contains($text, 'track') || str_contains($text, 'where is my order')) {
            return 'I can check your latest tracking update now. Please share your order number (example: DS-0000000001).';
        }

        if (str_contains($text, 'refund') || str_contains($text, 'return')) {
            return 'For returns and refunds, please share your order number and reason. I will review your order status and guide you on the next step.';
        }

        if (str_contains($text, 'payment') || str_contains($text, 'charged')) {
            return 'For payment issues, share your order number and payment method used. I will review the latest payment update for you.';
        }

        return null;
    }

    private function generateAiReply(SupportConversation $conversation, string $input, Customer $customer): string
    {
        if (! $this->deepSeek) {
            return 'Thanks for your message. Please share your order number if you need order or payment updates.';
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
                'content' => $this->isAiOnlyMode()
                    ? 'You are an ecommerce AI support assistant for Simbazu. Be concise, practical, and polite. AI handles this conversation fully. Never mention handing off to a human agent. Use provided order and payment snapshots when relevant, and only state facts from the snapshot.'
                    : 'You are an ecommerce support assistant for Simbazu. Be concise, practical, and polite. If an issue requires human action (refund approval, payment dispute, account security), explicitly say you are handing off to a human agent.',
            ],
            [
                'role' => 'user',
                'content' => 'Customer: ' . ($customer->name ?: 'Customer') . '. Conversation channel: ' . ($conversation->channel ?: 'mobile') . '.',
            ],
            [
                'role' => 'user',
                'content' => 'Trusted order/payment context: ' . json_encode(
                    $this->buildOrderContextSnapshot($customer, $input),
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                ),
            ],
            ...$history,
            [
                'role' => 'user',
                'content' => $input,
            ],
        ];

        $reply = trim($this->deepSeek->chat($messages, 0.4));

        return $reply !== '' ? $reply : 'I did not catch that. Could you share more details, including your order number if this is about an order or payment?';
    }

    private function isAiOnlyMode(): bool
    {
        return (bool) config('support_chat.ai_only', true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildOrderContextSnapshot(Customer $customer, string $input): array
    {
        $orderNumber = $this->extractOrderNumber($input);

        $orders = Order::query()
            ->where('customer_id', $customer->id)
            ->when(
                $orderNumber !== null,
                fn ($query) => $query->orderByRaw('number = ? desc', [$orderNumber])
            )
            ->latest('placed_at')
            ->latest('id')
            ->limit(5)
            ->get();

        return $orders->map(function (Order $order): array {
            $latestPayment = $order->payments()->latest('id')->first();
            $linehaul = $order->linehaulShipment()->first();
            $lastMile = $order->lastMileDelivery()->first();

            return [
                'order_number' => $order->number,
                'order_status' => $order->status,
                'customer_status' => $order->customer_status,
                'customer_status_label' => $order->getCustomerStatusLabel(),
                'payment_status' => $order->payment_status,
                'currency' => $order->currency,
                'grand_total' => (float) $order->grand_total,
                'placed_at' => optional($order->placed_at)->toIso8601String(),
                'cj_order_status' => $order->cj_order_status,
                'cj_payment_status' => $order->cj_payment_status,
                'tracking_number' => $linehaul?->tracking_number,
                'tracking_status' => $lastMile?->status ?? $linehaul?->cj_order_status,
                'tracking_url' => $linehaul?->cj_tracking_url,
                'latest_payment' => $latestPayment ? [
                    'provider' => $latestPayment->provider,
                    'status' => $latestPayment->status,
                    'amount' => (float) $latestPayment->amount,
                    'currency' => $latestPayment->currency,
                    'paid_at' => optional($latestPayment->paid_at)->toIso8601String(),
                    'reference' => $latestPayment->provider_reference,
                ] : null,
            ];
        })->values()->all();
    }

    private function buildOrderPaymentReply(Customer $customer, string $input): ?string
    {
        $text = strtolower($input);
        $orderNumber = $this->extractOrderNumber($input);
        $needsOrderLookup = str_contains($text, 'order')
            || str_contains($text, 'track')
            || str_contains($text, 'delivery')
            || str_contains($text, 'payment')
            || str_contains($text, 'charged')
            || str_contains($text, 'refund')
            || $orderNumber !== null;

        if (! $needsOrderLookup) {
            return null;
        }

        $order = Order::query()
            ->where('customer_id', $customer->id)
            ->when(
                $orderNumber !== null,
                fn ($query) => $query->where('number', $orderNumber),
                fn ($query) => $query->latest('placed_at')->latest('id')
            )
            ->first();

        if (! $order) {
            return $orderNumber !== null
                ? "I couldn't find order {$orderNumber} on your account. Please verify the order number."
                : 'I could not find an order on your account yet. If you placed one recently, share the exact order number so I can check again.';
        }

        $linehaul = $order->linehaulShipment()->first();
        $lastMile = $order->lastMileDelivery()->first();
        $latestPayment = $order->payments()->latest('id')->first();

        $trackingStatus = $lastMile?->status ?? $linehaul?->cj_order_status ?? $order->customer_status ?? $order->status;
        $trackingNumber = $linehaul?->tracking_number;
        $trackingSuffix = $trackingNumber
            ? " Tracking: {$trackingStatus} (No: {$trackingNumber})."
            : " Tracking: {$trackingStatus}.";

        $paymentStatus = $latestPayment?->status ?? $order->payment_status;
        $paymentSuffix = " Payment: {$paymentStatus}.";
        if ($latestPayment?->provider_reference) {
            $paymentSuffix .= " Ref: {$latestPayment->provider_reference}.";
        }

        return sprintf(
            'Latest update for order %s: %s.%s%s Total: %.2f %s.',
            (string) $order->number,
            (string) $order->getCustomerStatusLabel(),
            $paymentSuffix,
            $trackingSuffix,
            (float) $order->grand_total,
            (string) $order->currency
        );
    }

    private function extractOrderNumber(string $input): ?string
    {
        foreach ([
            '/\bDS-[A-Z0-9-]+\b/i',
            '/\b[A-Z]{2,6}-\d{4,}\b/i',
            '/#\s*([A-Z0-9-]{6,})/i',
        ] as $pattern) {
            if (preg_match($pattern, $input, $matches) === 1) {
                $value = $matches[1] ?? $matches[0] ?? null;
                if (is_string($value) && $value !== '') {
                    return strtoupper($value);
                }
            }
        }

        return null;
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
        if ($this->isAiOnlyMode()) {
            $ackText ??= 'AI support is handling this chat end-to-end right now. Please share your order number or payment details, and I will continue helping you here.';

            return $this->createAgentMessage(
                $conversation,
                'ai',
                $ackText,
                ['event' => 'ai_only_mode', 'reason' => $reason]
            );
        }

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

        $this->dispatchRealtimeMessage($conversation, $message);

        return $message;
    }

    private function createSystemMessage(
        SupportConversation $conversation,
        string $body,
        array $metadata = []
    ): SupportMessage {
        $message = $conversation->messages()->create([
            'sender_type' => 'system',
            'message_type' => 'text',
            'body' => trim($body),
            'metadata' => $metadata ?: null,
        ]);

        $this->dispatchRealtimeMessage($conversation, $message);

        return $message;
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

    private function dispatchRealtimeMessage(SupportConversation $conversation, SupportMessage $message): void
    {
        if (! (bool) config('support_chat.realtime.enabled', true)) {
            return;
        }

        if ((bool) ($message->is_internal_note ?? false)) {
            return;
        }

        event(new SupportMessageCreated(
            (string) $conversation->uuid,
            [
                'id' => (int) $message->id,
                'conversation_id' => (int) $message->conversation_id,
                'sender_type' => (string) $message->sender_type,
                'body' => (string) $message->body,
                'message_type' => (string) ($message->message_type ?? 'text'),
                'metadata' => is_array($message->metadata) ? $message->metadata : null,
                'is_internal_note' => (bool) ($message->is_internal_note ?? false),
                'read_at' => optional($message->read_at)?->toIso8601String(),
                'created_at' => optional($message->created_at)?->toIso8601String(),
            ]
        ));
    }
}
