<x-filament-panels::page>
    <style>
        .sc-shell {
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 1px 2px rgba(16, 24, 40, .06);
        }
        .dark .sc-shell { border-color: #1f2937; background: #111827; }
        .sc-layout { display: grid; grid-template-columns: 360px 1fr; min-height: 78vh; }
        .sc-sidebar { border-right: 1px solid #e5e7eb; background: #f8fafc; padding: 14px; }
        .dark .sc-sidebar { border-color: #1f2937; background: #0f172a; }
        .sc-sidebar-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .sc-title { font-size: 13px; font-weight: 700; letter-spacing: .02em; color: #374151; }
        .dark .sc-title { color: #d1d5db; }
        .sc-controls { display: grid; gap: 8px; margin-bottom: 12px; }
        .sc-head-meta { display: inline-flex; gap: 8px; align-items: center; }
        .sc-rt-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            border: 1px solid #d1d5db;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: 700;
            color: #6b7280;
            background: #fff;
        }
        .dark .sc-rt-badge { border-color: #374151; background: #111827; color: #d1d5db; }
        .sc-rt-dot { width: 8px; height: 8px; border-radius: 999px; background: #9ca3af; }
        .sc-rt-badge.is-connected .sc-rt-dot { background: #22c55e; }
        .sc-rt-badge.is-connecting .sc-rt-dot { background: #f59e0b; }
        .sc-rt-badge.is-disconnected .sc-rt-dot,
        .sc-rt-badge.is-unavailable .sc-rt-dot,
        .sc-rt-badge.is-auth_error .sc-rt-dot,
        .sc-rt-badge.is-error .sc-rt-dot { background: #ef4444; }
        .sc-input, .sc-select, .sc-textarea {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            background: #fff;
            color: #111827;
            font-size: 13px;
            line-height: 1.4;
            padding: 9px 12px;
        }
        .sc-input:focus, .sc-select:focus, .sc-textarea:focus { outline: 2px solid rgba(59,130,246,.15); border-color: #3b82f6; }
        .dark .sc-input, .dark .sc-select, .dark .sc-textarea { background: #0b1220; border-color: #374151; color: #e5e7eb; }
        .sc-list { max-height: calc(78vh - 140px); overflow: auto; padding-right: 4px; }
        .sc-section { margin-top: 16px; }
        .sc-section:first-child { margin-top: 0; }
        .sc-section-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; color: #6b7280; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }
        .sc-item {
            width: 100%; text-align: left; border: 1px solid #e5e7eb; border-radius: 12px; padding: 10px; background: #fff;
            transition: border-color .18s ease, box-shadow .18s ease;
            margin-bottom: 8px;
        }
        .sc-item:hover { border-color: #93c5fd; box-shadow: 0 1px 2px rgba(59,130,246,.12); }
        .sc-item.is-active { border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59,130,246,.15); background: #eff6ff; }
        .dark .sc-item { border-color: #374151; background: #111827; }
        .dark .sc-item.is-active { border-color: #60a5fa; background: rgba(30,64,175,.2); }
        .sc-item-row { display: flex; gap: 10px; align-items: flex-start; }
        .sc-avatar {
            width: 36px; height: 36px; border-radius: 999px; display: grid; place-items: center; color: #fff;
            font-size: 13px; font-weight: 700; background: linear-gradient(135deg, #3b82f6, #2563eb); position: relative; flex: 0 0 auto;
        }
        .sc-dot {
            position: absolute; top: -1px; right: -1px; width: 10px; height: 10px; border-radius: 999px;
            border: 2px solid #fff; background: #22c55e;
        }
        .sc-dot.is-offline { background: #9ca3af; }
        .sc-item-main { min-width: 0; flex: 1; }
        .sc-item-top { display: flex; justify-content: space-between; gap: 8px; }
        .sc-item-name { font-size: 13px; font-weight: 700; color: #111827; white-space: nowrap; text-overflow: ellipsis; overflow: hidden; }
        .dark .sc-item-name { color: #f3f4f6; }
        .sc-item-sub { margin-top: 1px; font-size: 12px; color: #6b7280; white-space: nowrap; text-overflow: ellipsis; overflow: hidden; }
        .sc-item-meta { margin-top: 6px; display: flex; justify-content: space-between; align-items: center; font-size: 11px; color: #6b7280; }
        .sc-unread { min-width: 18px; padding: 0 6px; height: 18px; border-radius: 999px; background: #ef4444; color: #fff; font-size: 10px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; }
        .sc-chat { display: flex; flex-direction: column; min-height: 78vh; }
        .sc-chat-head {
            border-bottom: 1px solid #e5e7eb; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; gap: 12px;
            background: #fff;
        }
        .dark .sc-chat-head { border-color: #1f2937; background: #111827; }
        .sc-chat-user { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .sc-chat-name { font-size: 14px; font-weight: 700; color: #111827; }
        .dark .sc-chat-name { color: #f3f4f6; }
        .sc-chat-email { font-size: 12px; color: #6b7280; white-space: nowrap; text-overflow: ellipsis; overflow: hidden; max-width: 360px; }
        .sc-chat-body { flex: 1; overflow: auto; padding: 16px; background: #fff; }
        .dark .sc-chat-body { background: #111827; }
        .sc-msg-wrap { display: flex; margin-bottom: 14px; }
        .sc-msg-wrap.is-out { justify-content: flex-end; }
        .sc-msg { max-width: 72%; }
        .sc-msg-meta { font-size: 11px; color: #6b7280; margin-bottom: 4px; display: flex; gap: 8px; align-items: center; }
        .sc-msg-wrap.is-out .sc-msg-meta { justify-content: flex-end; }
        .sc-bubble {
            border-radius: 16px; padding: 10px 13px; line-height: 1.45; font-size: 13px;
            border: 1px solid #e5e7eb; background: #f3f4f6; color: #111827;
        }
        .sc-msg-wrap.is-out .sc-bubble { background: #1d9bf0; border-color: #1d9bf0; color: #fff; }
        .sc-bubble.is-note { background: #fffbeb; border-color: #fde68a; color: #92400e; }
        .dark .sc-bubble { border-color: #374151; background: #1f2937; color: #f3f4f6; }
        .dark .sc-msg-wrap.is-out .sc-bubble { background: #1d9bf0; border-color: #1d9bf0; color: #fff; }
        .sc-bubble img { max-height: 160px; border-radius: 10px; margin-top: 8px; }
        .sc-replies { border-top: 1px solid #e5e7eb; background: #f8fafc; padding: 10px 16px; display: flex; gap: 6px; flex-wrap: wrap; }
        .dark .sc-replies { border-color: #1f2937; background: #0f172a; }
        .sc-reply-btn {
            border: 1px solid #d1d5db; background: #fff; color: #374151; border-radius: 999px; padding: 4px 10px; font-size: 11px; font-weight: 600;
        }
        .sc-reply-btn:hover { border-color: #93c5fd; color: #1d4ed8; }
        .dark .sc-reply-btn { border-color: #374151; background: #111827; color: #d1d5db; }
        .sc-compose { border-top: 1px solid #e5e7eb; background: #fff; padding: 12px 16px; }
        .dark .sc-compose { border-color: #1f2937; background: #111827; }
        .sc-compose-grid { display: grid; gap: 10px; grid-template-columns: 1fr 250px; }
        .sc-compose-side { display: flex; flex-direction: column; gap: 10px; }
        .sc-error { margin-top: 6px; color: #dc2626; font-size: 12px; }
        @media (max-width: 1280px) {
            .sc-layout { grid-template-columns: 1fr; }
            .sc-sidebar { border-right: 0; border-bottom: 1px solid #e5e7eb; }
            .dark .sc-sidebar { border-bottom-color: #1f2937; }
            .sc-compose-grid { grid-template-columns: 1fr; }
            .sc-chat-email { max-width: 240px; }
        }
    </style>

    <div
        class="sc-shell"
        wire:poll.8s="refreshData"
        x-data="{ realtimeStatus: window.__supportChatAdminRealtimeStatus ?? 'connecting' }"
        x-on:support-chat-admin-realtime-status.window="realtimeStatus = ($event.detail && $event.detail.status) ? $event.detail.status : 'connecting'"
    >
        <div class="sc-layout">
            <aside class="sc-sidebar">
                <div class="sc-sidebar-head">
                    <h2 class="sc-title">Support Inbox</h2>
                    <div class="sc-head-meta">
                        <span class="sc-rt-badge" :class="`is-${realtimeStatus}`">
                            <span class="sc-rt-dot"></span>
                            <span x-text="({
                                connected: 'Realtime on',
                                connecting: 'Realtime connecting',
                                disconnected: 'Realtime off',
                                unavailable: 'Realtime unavailable',
                                auth_error: 'Realtime auth error',
                                error: 'Realtime error',
                            }[realtimeStatus] ?? 'Realtime connecting')">
                                Realtime connecting
                            </span>
                        </span>
                        <x-filament::badge color="gray">{{ $this->conversations->count() }}</x-filament::badge>
                    </div>
                </div>

                <div class="sc-controls">
                    <select wire:model.live="conversationSort" class="sc-select">
                        @foreach ($this->conversationSortOptions() as $value => $label)
                            <option value="{{ $value }}">Sort: {{ $label }}</option>
                        @endforeach
                    </select>
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search customer, email, topic, UUID" class="sc-input" />
                    <select wire:model.live="statusFilter" class="sc-select">
                        @foreach ($this->statusOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="sc-list">
                    @foreach ($this->conversationSections as $section)
                        @php $sectionItems = $section['items']; @endphp
                        <section class="sc-section">
                            <div class="sc-section-head">
                                <span>{{ $section['label'] }}</span>
                                <x-filament::badge color="gray">{{ $sectionItems->count() }}</x-filament::badge>
                            </div>
                            @forelse ($sectionItems as $conversation)
                                @php
                                    $isSelected = $selectedConversationId === $conversation->id;
                                    $unreadCount = (int) ($conversation->unread_for_admin_count ?? 0);
                                    $customerName = trim((string) ($conversation->customer?->name ?? ''));
                                    $customerEmail = trim((string) ($conversation->customer?->email ?? ''));
                                    $initial = strtoupper(substr($customerName !== '' ? $customerName : 'G', 0, 1));
                                    $isOnline = in_array((string) $conversation->status, ['pending_agent', 'open', 'pending_customer'], true);
                                @endphp
                                <button
                                    type="button"
                                    wire:key="support-conversation-{{ $conversation->id }}"
                                    wire:click="selectConversation({{ $conversation->id }})"
                                    @class(['sc-item', 'is-active' => $isSelected])
                                >
                                    <div class="sc-item-row">
                                        <div class="sc-avatar">
                                            {{ $initial }}
                                            <span @class(['sc-dot', 'is-offline' => ! $isOnline])></span>
                                        </div>
                                        <div class="sc-item-main">
                                            <div class="sc-item-top">
                                                <p class="sc-item-name">{{ $customerName !== '' ? $customerName : 'Guest customer' }}</p>
                                                @if ($unreadCount > 0)
                                                    <span class="sc-unread">{{ $unreadCount }}</span>
                                                @endif
                                            </div>
                                            <p class="sc-item-sub">{{ $customerEmail !== '' ? $customerEmail : ($conversation->topic ?: 'No topic yet') }}</p>
                                            <div class="sc-item-meta">
                                                <span>{{ optional($conversation->last_message_at ?? $conversation->created_at)->timezone(config('app.timezone'))->format('M j, H:i') }}</span>
                                                <x-filament::badge :color="$this->statusBadgeColor((string) $conversation->status)">
                                                    {{ $this->statusLabel((string) $conversation->status) }}
                                                </x-filament::badge>
                                            </div>
                                        </div>
                                    </div>
                                </button>
                            @empty
                                <div class="sc-item-sub">No conversations</div>
                            @endforelse
                        </section>
                    @endforeach
                </div>
            </aside>

            <main class="sc-chat">
                @if ($this->selectedConversation)
                    @php
                        $selectedConversation = $this->selectedConversation;
                        $selectedCustomerName = trim((string) ($selectedConversation->customer?->name ?? ''));
                        $selectedCustomerEmail = trim((string) ($selectedConversation->customer?->email ?? ''));
                        $selectedInitial = strtoupper(substr($selectedCustomerName !== '' ? $selectedCustomerName : 'G', 0, 1));
                    @endphp

                    <header class="sc-chat-head">
                        <div class="sc-chat-user">
                            <div class="sc-avatar">
                                {{ $selectedInitial }}
                                <span class="sc-dot"></span>
                            </div>
                            <div>
                                <p class="sc-chat-name">{{ $selectedCustomerName !== '' ? $selectedCustomerName : 'Guest customer' }}</p>
                                <p class="sc-chat-email">{{ $selectedCustomerEmail !== '' ? $selectedCustomerEmail : 'No email available' }}</p>
                            </div>
                        </div>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <span class="sc-rt-badge" :class="`is-${realtimeStatus}`">
                                    <span class="sc-rt-dot"></span>
                                    <span x-text="({
                                        connected: 'Realtime',
                                        connecting: 'Realtime…',
                                        disconnected: 'Polling',
                                        unavailable: 'Polling',
                                        auth_error: 'Auth issue',
                                        error: 'Network issue',
                                    }[realtimeStatus] ?? 'Realtime…')">
                                        Realtime…
                                    </span>
                                </span>
                                <x-filament::badge :color="$this->statusBadgeColor((string) $selectedConversation->status)">
                                    {{ $this->statusLabel((string) $selectedConversation->status) }}
                                </x-filament::badge>
                            <x-filament::button color="gray" size="sm" wire:click="assignSelectedToMe" wire:loading.attr="disabled">Assign to me</x-filament::button>
                            <x-filament::button color="danger" size="sm" wire:click="resolveSelectedConversation" wire:loading.attr="disabled">Stop chat</x-filament::button>
                        </div>
                    </header>

                    <section
                        id="support-chat-scroll"
                        class="sc-chat-body"
                        x-data="{ scrollToBottom() { this.$el.scrollTop = this.$el.scrollHeight } }"
                        x-init="$nextTick(() => scrollToBottom()); new MutationObserver(() => scrollToBottom()).observe($el, { childList: true, subtree: true })"
                    >
                        @foreach ($this->messages as $message)
                            @php
                                $senderType = (string) $message->sender_type;
                                $isInternal = (bool) $message->is_internal_note;
                                $isOutbound = in_array($senderType, ['agent', 'ai'], true) || $isInternal;
                                $attachmentUrl = (string) (data_get($message, 'metadata.attachment_url') ?? '');
                                $attachmentName = (string) (data_get($message, 'metadata.attachment_name') ?? '');
                                $attachmentType = (string) (data_get($message, 'metadata.attachment_type') ?? '');
                                $attachmentMime = (string) (data_get($message, 'metadata.attachment_mime') ?? '');
                                $isImageAttachment = $attachmentUrl !== '' && ($attachmentType === 'image' || str_starts_with($attachmentMime, 'image/'));
                                $senderLabel = match ($senderType) {
                                    'customer' => 'Customer',
                                    'agent' => (string) ($message->senderUser?->name ?: 'Agent'),
                                    'ai' => 'AI Assistant',
                                    default => 'System',
                                };
                            @endphp
                            <div @class(['sc-msg-wrap', 'is-out' => $isOutbound])>
                                <div class="sc-msg">
                                    <div class="sc-msg-meta">
                                        <span>{{ $senderLabel }}</span>
                                        <span>{{ optional($message->created_at)->timezone(config('app.timezone'))->format('H:i') }}</span>
                                    </div>
                                    <div @class(['sc-bubble', 'is-note' => $isInternal])>
                                        @if (trim((string) $message->body) !== '')
                                            <p style="white-space:pre-line;margin:0;">{{ $message->body }}</p>
                                        @endif
                                        @if ($attachmentUrl !== '')
                                            @if ($isImageAttachment)
                                                <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener noreferrer">
                                                    <img src="{{ $attachmentUrl }}" alt="{{ $attachmentName !== '' ? $attachmentName : 'Attachment' }}" loading="lazy" />
                                                </a>
                                            @endif
                                            <div style="margin-top:8px;">
                                                <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener noreferrer" style="font-size:12px;text-decoration:underline;">
                                                    {{ $attachmentName !== '' ? $attachmentName : 'Open attachment' }}
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </section>

                    <div class="sc-replies">
                        @foreach ($this->quickReplies() as $quickReply)
                            <button type="button" class="sc-reply-btn" wire:click='useQuickReply(@js($quickReply))'>
                                {{ $quickReply }}
                            </button>
                        @endforeach
                    </div>

                    <footer class="sc-compose">
                        <div class="sc-compose-grid">
                            <textarea wire:model.defer="replyBody" rows="3" class="sc-textarea" placeholder="Type your message..."></textarea>
                            <div class="sc-compose-side">
                                <input type="file" wire:model="replyAttachment" class="sc-input" accept="image/jpeg,image/png,image/webp,application/pdf,text/plain" />
                                <label style="display:inline-flex;align-items:center;gap:8px;font-size:12px;color:#6b7280;">
                                    <x-filament::input.checkbox wire:model.defer="replyInternalNote" />
                                    Internal note
                                </label>
                                <x-filament::button wire:click="sendReply" wire:loading.attr="disabled" icon="heroicon-o-paper-airplane">Send</x-filament::button>
                            </div>
                        </div>
                        @error('replyBody')<p class="sc-error">{{ $message }}</p>@enderror
                        @error('replyAttachment')<p class="sc-error">{{ $message }}</p>@enderror
                    </footer>
                @else
                    <div style="display:grid;place-items:center;height:100%;padding:40px;color:#6b7280;">
                        Select a conversation to start chatting.
                    </div>
                @endif
            </main>
        </div>
    </div>
</x-filament-panels::page>
