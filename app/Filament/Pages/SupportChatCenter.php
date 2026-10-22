<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Support\Models\SupportConversation;
use App\Domain\Support\Models\SupportMessage;
use App\Domain\Support\Services\SupportAttachmentService;
use App\Domain\Support\Services\SupportChatService;
use App\Models\User;
use BackedEnum;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\WithFileUploads;
use UnitEnum;

class SupportChatCenter extends BasePage
{
    use WithFileUploads;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static UnitEnum|string|null $navigationGroup = 'Messaging';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.support-chat-center';

    public ?int $selectedConversationId = null;

    public string $search = '';

    public string $statusFilter = 'all';

    public string $conversationSort = 'newest';

    public string $replyBody = '';

    public bool $replyInternalNote = false;

    public mixed $replyAttachment = null;

    public static function getNavigationLabel(): string
    {
        return 'Support Chat';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = SupportConversation::query()
            ->where(function (Builder $query): void {
                $query
                    ->where('status', 'pending_agent')
                    ->orWhere(function (Builder $inner): void {
                        $inner->where('handoff_requested', true)->whereIn('status', ['open', 'pending_agent']);
                    });
            })
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    protected function getListeners(): array
    {
        return [
            'echo-private:support.admin,support.message.created' => 'handleSupportMessageCreated',
        ];
    }

    public function mount(): void
    {
        $requestedConversation = request()->integer('conversation');
        if ($requestedConversation > 0) {
            $this->selectedConversationId = $requestedConversation;
        }

        if (! $this->selectedConversationId) {
            $this->selectedConversationId = $this->baseConversationQuery()->value('id');
        }

        $this->markSelectedConversationRead();
    }

    public function handleSupportMessageCreated(array $payload = []): void
    {
        $eventConversationUuid = (string) data_get($payload, 'conversation_uuid', '');
        if ($eventConversationUuid === '') {
            return;
        }

        if (! $this->selectedConversationId) {
            $this->selectedConversationId = SupportConversation::query()
                ->where('uuid', $eventConversationUuid)
                ->value('id');
        }

        $this->markSelectedConversationRead();
    }

    public function refreshData(): void
    {
        $this->markSelectedConversationRead();
    }

    public function useQuickReply(string $text): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }

        $current = trim($this->replyBody);
        $this->replyBody = $current === '' ? $text : ($current . PHP_EOL . $text);
    }

    public function selectConversation(int $conversationId): void
    {
        $this->selectedConversationId = $conversationId;
        $this->markSelectedConversationRead();
    }

    public function sendReply(): void
    {
        $conversation = $this->selectedConversation;
        if (! $conversation instanceof SupportConversation) {
            Notification::make()
                ->warning()
                ->title('Select a conversation first')
                ->send();

            return;
        }

        $body = trim($this->replyBody);
        $this->validate($this->messageValidationRules());

        if ($body === '' && ! $this->replyAttachment) {
            Notification::make()
                ->warning()
                ->title('Message or attachment is required')
                ->send();

            return;
        }

        $admin = auth(config('filament.auth.guard', 'admin'))->user();
        if (! $admin instanceof User) {
            Notification::make()
                ->danger()
                ->title('Admin session required')
                ->send();

            return;
        }

        try {
            if ($this->replyAttachment) {
                $attachment = app(SupportAttachmentService::class)->store($this->replyAttachment);
                app(SupportChatService::class)->addAdminAttachmentReply(
                    $conversation,
                    $admin,
                    $attachment,
                    $body !== '' ? $body : null,
                    $this->replyInternalNote
                );
            } else {
                app(SupportChatService::class)->addAdminReply(
                    $conversation,
                    $admin,
                    $body,
                    $this->replyInternalNote
                );
            }
        } catch (\Illuminate\Validation\ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title('Attachment validation failed')
                ->body((string) collect($exception->errors())->flatten()->first())
                ->send();

            return;
        }

        $this->replyBody = '';
        $this->replyInternalNote = false;
        $this->replyAttachment = null;
        $this->markSelectedConversationRead();

        Notification::make()
            ->success()
            ->title('Reply sent')
            ->send();
    }

    public function assignSelectedToMe(): void
    {
        $conversation = $this->selectedConversation;
        $admin = auth(config('filament.auth.guard', 'admin'))->user();

        if (! $conversation instanceof SupportConversation || ! $admin instanceof User) {
            return;
        }

        app(SupportChatService::class)->assignToAdmin($conversation, $admin);

        Notification::make()
            ->success()
            ->title('Conversation assigned to you')
            ->send();
    }

    public function resolveSelectedConversation(): void
    {
        $conversation = $this->selectedConversation;
        if (! $conversation instanceof SupportConversation) {
            return;
        }

        app(SupportChatService::class)->markResolved($conversation);

        Notification::make()
            ->success()
            ->title('Conversation marked as resolved')
            ->send();
    }

    /**
     * @return Collection<int, SupportConversation>
     */
    public function getConversationsProperty(): Collection
    {
        $query = $this->baseConversationQuery()
            ->with([
                'customer:id,first_name,last_name,email',
                'assignedUser:id,name',
            ])
            ->withCount([
                'messages as unread_for_admin_count' => fn (Builder $query): Builder => $query
                    ->where('is_internal_note', false)
                    ->where('sender_type', 'customer')
                    ->whereNull('read_at'),
            ]);

        $this->applyConversationOrdering($query);

        return $query
            ->limit(150)
            ->get();
    }

    public function getSelectedConversationProperty(): ?SupportConversation
    {
        if (! $this->selectedConversationId) {
            return null;
        }

        return SupportConversation::query()
            ->with(['customer:id,first_name,last_name,email', 'assignedUser:id,name'])
            ->find($this->selectedConversationId);
    }

    /**
     * @return Collection<int, SupportMessage>
     */
    public function getMessagesProperty(): Collection
    {
        if (! $this->selectedConversationId) {
            return collect();
        }

        return SupportMessage::query()
            ->where('conversation_id', $this->selectedConversationId)
            ->with(['senderUser:id,name', 'senderCustomer:id,first_name,last_name'])
            ->orderByDesc('id')
            ->limit(250)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * @return array<int, string>
     */
    public function statusOptions(): array
    {
        return [
            'all' => 'All statuses',
            'open' => 'Open',
            'pending_agent' => 'Pending Agent',
            'pending_customer' => 'Pending Customer',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function conversationSortOptions(): array
    {
        return [
            'newest' => 'Newest',
            'oldest' => 'Oldest',
            'priority' => 'Priority',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function quickReplies(): array
    {
        return [
            'Merci pour votre message. Nous vérifions cela maintenant.',
            'Pouvez-vous partager votre numéro de commande, s’il vous plaît ?',
            'Nous avons escaladé ce dossier à notre équipe logistique.',
            'Le remboursement est en cours de traitement.',
            'Merci pour votre patience. Nous revenons vers vous sous peu.',
        ];
    }

    /**
     * @return array<int, array{label: string, items: Collection<int, SupportConversation>}>
     */
    public function getConversationSectionsProperty(): array
    {
        $conversations = $this->conversations;
        $new = $conversations
            ->filter(fn (SupportConversation $conversation): bool => in_array((string) $conversation->status, ['pending_agent', 'open'], true))
            ->values();
        $adminId = auth(config('filament.auth.guard', 'admin'))->id();
        $my = $conversations
            ->filter(fn (SupportConversation $conversation): bool => (int) $conversation->assigned_user_id === (int) $adminId)
            ->values();
        $myIds = $my->pluck('id')->all();
        $newIds = $new->pluck('id')->all();
        $other = $conversations
            ->reject(fn (SupportConversation $conversation): bool => in_array($conversation->id, $myIds, true) || in_array($conversation->id, $newIds, true))
            ->values();

        return [
            ['label' => 'New chats', 'items' => $new],
            ['label' => 'My chats', 'items' => $my],
            ['label' => 'Other chats', 'items' => $other],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function messageValidationRules(): array
    {
        $maxKb = max(1024, (int) config('support_chat.attachments.max_kb', 10240));
        $allowedMimes = app(SupportAttachmentService::class)->allowedMimes();
        $mimeRule = $allowedMimes !== []
            ? 'mimetypes:' . implode(',', $allowedMimes)
            : null;

        return [
            'replyBody' => ['nullable', 'string', 'max:4000'],
            'replyInternalNote' => ['boolean'],
            'replyAttachment' => array_values(array_filter(['nullable', 'file', 'max:' . $maxKb, $mimeRule])),
        ];
    }

    private function markSelectedConversationRead(): void
    {
        if (! $this->selectedConversationId) {
            return;
        }

        $conversation = SupportConversation::query()->find($this->selectedConversationId);
        if (! $conversation) {
            return;
        }

        app(SupportChatService::class)->markMessagesReadByAdmin($conversation);
    }

    private function baseConversationQuery(): Builder
    {
        $query = SupportConversation::query();

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function (Builder $inner) use ($search): void {
                $inner
                    ->where('uuid', 'like', "%{$search}%")
                    ->orWhere('topic', 'like', "%{$search}%")
                    ->orWhereHas('customer', function (Builder $customerQuery) use ($search): void {
                        $customerQuery
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhereRaw(
                                "TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))) like ?",
                                ["%{$search}%"]
                            );
                    });
            });
        }

        return $query;
    }

    private function applyConversationOrdering(Builder $query): void
    {
        if ($this->conversationSort === 'oldest') {
            $query->orderByRaw("
                CASE
                    WHEN status = 'pending_agent' THEN 0
                    WHEN status = 'open' THEN 1
                    WHEN status = 'pending_customer' THEN 2
                    WHEN status = 'resolved' THEN 3
                    ELSE 4
                END
            ");
            $query->orderBy('last_message_at');

            return;
        }

        if ($this->conversationSort === 'priority') {
            $query->orderByRaw("
                CASE
                    WHEN priority = 'urgent' THEN 0
                    WHEN priority = 'high' THEN 1
                    WHEN priority = 'normal' THEN 2
                    ELSE 3
                END
            ");
            $query->orderByRaw("
                CASE
                    WHEN status = 'pending_agent' THEN 0
                    WHEN status = 'open' THEN 1
                    WHEN status = 'pending_customer' THEN 2
                    WHEN status = 'resolved' THEN 3
                    ELSE 4
                END
            ");
            $query->orderByDesc('last_message_at');

            return;
        }

        $query->orderByRaw("
            CASE
                WHEN status = 'pending_agent' THEN 0
                WHEN status = 'open' THEN 1
                WHEN status = 'pending_customer' THEN 2
                WHEN status = 'resolved' THEN 3
                ELSE 4
            END
        ");
        $query->orderByDesc('last_message_at');
    }

    public function statusBadgeColor(string $status): string
    {
        return match ($status) {
            'open' => 'info',
            'pending_agent' => 'warning',
            'pending_customer' => 'success',
            'resolved' => 'gray',
            'closed' => 'danger',
            default => 'gray',
        };
    }

    public function statusLabel(string $status): string
    {
        return Str::headline($status);
    }
}
