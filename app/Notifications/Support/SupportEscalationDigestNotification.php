<?php

declare(strict_types=1);

namespace App\Notifications\Support;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupportEscalationDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param array<string, mixed> $summary
     */
    public function __construct(
        private readonly array $summary,
    ) {
        $this->onQueue((string) config('support_chat.queue', 'support'));
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        $email = method_exists($notifiable, 'getAttribute')
            ? $notifiable->getAttribute('email')
            : null;

        if (is_string($email) && trim($email) !== '') {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'support_escalation_digest',
            'generated_at' => now()->toIso8601String(),
            'summary' => $this->summary,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $overdueCount = (int) ($this->summary['overdue_count'] ?? 0);
        $unassignedCount = (int) ($this->summary['unassigned_overdue_count'] ?? 0);
        $oldestMinutes = (int) ($this->summary['oldest_wait_minutes'] ?? 0);
        $oldestConversationId = (int) ($this->summary['oldest_conversation_id'] ?? 0);
        $items = is_array($this->summary['items'] ?? null) ? $this->summary['items'] : [];
        $slaMinutes = (int) ($this->summary['sla_minutes'] ?? 15);
        $url = rtrim((string) config('app.url'), '/') . '/admin/support-conversations';

        $mail = (new MailMessage())
            ->subject('Support SLA Digest: ' . $overdueCount . ' overdue')
            ->line("Overdue conversations: {$overdueCount}")
            ->line("Unassigned overdue: {$unassignedCount}")
            ->line("SLA threshold: {$slaMinutes} minutes");

        if ($oldestConversationId > 0) {
            $mail->line("Oldest wait: {$oldestMinutes} min (Conversation #{$oldestConversationId})");
        }

        if ($items !== []) {
            $mail->line('Top overdue conversations:');

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $conversationId = (int) ($item['conversation_id'] ?? 0);
                $waitMinutes = (int) ($item['wait_minutes'] ?? 0);
                $customer = (string) ($item['customer'] ?? 'Unknown');
                $status = (string) ($item['status'] ?? 'pending_agent');
                $mail->line("#{$conversationId} | {$waitMinutes} min | {$customer} | {$status}");
            }
        }

        return $mail->action('Open Support Inbox', $url);
    }
}
