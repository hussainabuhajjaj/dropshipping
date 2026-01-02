<?php

namespace App\Domain\Messaging\Services;

use App\Domain\Messaging\Models\MessageLog;
use App\Domain\Messaging\Models\MessageTemplate;
use App\Domain\Messaging\Models\MessageTriggerHistory;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\Shipment;
use App\Enums\MessageChannel;
use App\Enums\MessageTriggerType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

class MessageTemplateService
{
    /**
     * Send template to customer manually.
     */
    public function sendTemplate(
        MessageTemplate $template,
        Order $order,
        array $placeholders = [],
        MessageChannel $channel = null,
        ?Shipment $shipment = null,
        ?string $recipient = null
    ): MessageLog {
        // Validate required placeholders
        if (!$template->hasAllRequiredPlaceholders($placeholders)) {
            throw new \InvalidArgumentException('Missing required placeholders');
        }

        $channel = $channel ?? $template->default_channel;
        $recipient = $recipient ?? $order->customer?->email ?? $order->email;

        // Fill placeholders
        $messageContent = $template->fillPlaceholders($placeholders);
        $subject = $template->subject ? $template->fillPlaceholders(json_decode($template->subject, true)) : null;

        // Create log
        $log = MessageLog::create([
            'message_template_id' => $template->id,
            'order_id' => $order->id,
            'shipment_id' => $shipment?->id,
            'customer_id' => $order->customer_id,
            'recipient' => $recipient,
            'channel' => $channel->value,
            'subject' => $subject,
            'message_content' => $messageContent,
            'placeholders_used' => $placeholders,
            'status' => 'sent',
            'sent_at' => now(),
            'sent_by' => auth()->id(),
            'is_automatic' => false,
        ]);

        // Send via channel
        $this->sendViaChannel($log, $channel);

        return $log;
    }

    /**
     * Send via specified channel.
     */
    private function sendViaChannel(MessageLog $log, MessageChannel $channel): void
    {
        match ($channel) {
            MessageChannel::EMAIL => $this->sendEmail($log),
            MessageChannel::WHATSAPP => $this->sendWhatsApp($log),
            MessageChannel::SMS => $this->sendSms($log),
            MessageChannel::MANUAL => null, // Manual send
        };
    }

    /**
     * Send email.
     */
    private function sendEmail(MessageLog $log): void
    {
        try {
            // TODO: Implement email sending
            // Mail::to($log->recipient)->send(new \App\Mail\CustomerMessageMail($log));
            $log->update(['status' => 'sent']);
        } catch (\Exception $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send WhatsApp.
     */
    private function sendWhatsApp(MessageLog $log): void
    {
        try {
            // TODO: Integrate WhatsApp provider (Twilio, etc)
            $log->update(['status' => 'sent']);
        } catch (\Exception $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send SMS.
     */
    private function sendSms(MessageLog $log): void
    {
        try {
            // TODO: Integrate SMS provider
            $log->update(['status' => 'sent']);
        } catch (\Exception $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Recommend templates for trigger event.
     */
    public function recommendTemplatesForTrigger(
        MessageTriggerType $trigger,
        Order $order,
        ?Shipment $shipment = null
    ): \Illuminate\Support\Collection {
        return MessageTemplate::query()
            ->where('is_active', true)
            ->where(function ($query) use ($trigger) {
                $query->whereRaw("JSON_CONTAINS(trigger_types, JSON_QUOTE(?))", [$trigger->value])
                    ->orWhereNull('trigger_types');
            })
            ->get()
            ->filter(fn ($template) => $this->matchesConditions($template, $order, $shipment));
    }

    /**
     * Check if template conditions match order/shipment.
     */
    private function matchesConditions(MessageTemplate $template, Order $order, ?Shipment $shipment): bool
    {
        // TODO: Implement condition rule matching
        // For now, return true to recommend all templates
        return true;
    }

    /**
     * Schedule auto-send based on trigger.
     */
    public function scheduleAutoSend(
        MessageTriggerType $trigger,
        Order $order,
        array $placeholders = [],
        ?Shipment $shipment = null,
        array $triggerData = []
    ): void {
        $templates = $this->recommendTemplatesForTrigger($trigger, $order, $shipment);

        foreach ($templates as $template) {
            if (!$template->hasAutoSend()) {
                continue;
            }

            $scheduledFor = now();
            if ($template->auto_send_delay_hours) {
                $scheduledFor = now()->addHours($template->auto_send_delay_hours);
            }

            MessageTriggerHistory::create([
                'message_template_id' => $template->id,
                'order_id' => $order->id,
                'shipment_id' => $shipment?->id,
                'trigger_type' => $trigger->value,
                'trigger_data' => $triggerData,
                'status' => 'scheduled',
                'scheduled_for' => $scheduledFor,
            ]);
        }
    }

    /**
     * Process scheduled messages.
     */
    public function processScheduledMessages(): void
    {
        $scheduled = MessageTriggerHistory::query()
            ->where('status', 'scheduled')
            ->where('scheduled_for', '<=', now())
            ->with(['messageTemplate', 'order', 'shipment'])
            ->get();

        foreach ($scheduled as $trigger) {
            try {
                $placeholders = $trigger->trigger_data ?? [];

                $log = $this->sendTemplate(
                    $trigger->messageTemplate,
                    $trigger->order,
                    $placeholders,
                    null,
                    $trigger->shipment
                );

                $trigger->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'message_log_id' => $log->id,
                ]);
            } catch (\Exception $e) {
                $trigger->update([
                    'status' => 'failed',
                    'cancellation_reason' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Get message history for order.
     */
    public function getOrderMessageHistory(Order $order): \Illuminate\Support\Collection
    {
        return $order->messageLogs()
            ->with(['messageTemplate', 'shipment', 'sentByUser'])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get message history for shipment.
     */
    public function getShipmentMessageHistory(Shipment $shipment): \Illuminate\Support\Collection
    {
        return $shipment->messageLogs()
            ->with(['messageTemplate', 'sentByUser'])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get templates by type.
     */
    public function getTemplatesByType(string $type): \Illuminate\Support\Collection
    {
        return MessageTemplate::where('type', $type)
            ->where('is_active', true)
            ->orderBy('title')
            ->get();
    }

    /**
     * Get message statistics.
     */
    public function getMessageStatistics(Order $order): array
    {
        $logs = $order->messageLogs;

        return [
            'total' => $logs->count(),
            'sent' => $logs->where('status', 'sent')->count(),
            'failed' => $logs->where('status', 'failed')->count(),
            'opened' => $logs->whereNotNull('opened_at')->count(),
            'by_channel' => $logs->groupBy('channel')->map(fn ($group) => $group->count())->toArray(),
            'by_template' => $logs->groupBy('message_template_id')
                ->map(fn ($group) => $group->count())
                ->toArray(),
        ];
    }

    /**
     * Get recommended templates for exception.
     */
    public function recommendTemplatesForException(
        string $exceptionCode,
        Order $order,
        ?Shipment $shipment = null
    ): \Illuminate\Support\Collection {
        // Map exception codes to trigger types
        $triggerMap = [
            'CUSTOMS_DUTY_UNPAID' => MessageTriggerType::CUSTOMS_HOLD,
            'CUSTOMS_CLEARANCE_REQUIRED' => MessageTriggerType::CUSTOMS_HOLD,
            'CUSTOMS_HELD' => MessageTriggerType::CUSTOMS_HOLD,
            'RETURNED_TO_SENDER' => MessageTriggerType::SHIPMENT_DELAYED,
            'DELIVERY_FAILED' => MessageTriggerType::SHIPMENT_DELAYED,
            'TRACKING_NO_UPDATES' => MessageTriggerType::SHIPMENT_DELAYED,
            'PACKAGE_DAMAGED' => MessageTriggerType::EXCEPTION_OCCURRED,
            'PACKAGE_LOST' => MessageTriggerType::EXCEPTION_OCCURRED,
        ];

        $trigger = $triggerMap[$exceptionCode] ?? MessageTriggerType::EXCEPTION_OCCURRED;

        return $this->recommendTemplatesForTrigger($trigger, $order, $shipment);
    }

    /**
     * Test template with sample placeholders.
     */
    public function testTemplate(MessageTemplate $template, array $samplePlaceholders = []): string
    {
        $defaults = [
            'order_number' => 'ORD-12345',
            'order_id' => '1',
            'customer_name' => 'John Doe',
            'tracking_number' => '1Z999AA10123456784',
            'carrier' => 'UPS',
            'estimated_delivery' => now()->addDays(5)->format('M d, Y'),
            'refund_amount' => '$99.99',
            'exception_type' => 'Customs Hold',
        ];

        $placeholders = array_merge($defaults, $samplePlaceholders);

        return $template->fillPlaceholders($placeholders);
    }
}
